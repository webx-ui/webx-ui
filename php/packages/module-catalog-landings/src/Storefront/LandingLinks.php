<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Storefront;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\Routing\Models\Route;

/**
 * The links between the catalogue's pages (§7 of the landings spec), four lists:
 *
 * - «Collections» on a category: its landings marked `on_category`;
 * - the neighbours of a landing: the other landings of its base;
 * - the same set on other categories;
 * - «In collections» beside a product: the landings whose list holds it.
 *
 * Only published landings that are not known to be empty get links. Each list is one query; the
 * product's is one query for the candidates and its document from the indexing's own builder,
 * matched in memory. The limits are `webx-catalog-landings.links.*`.
 */
final class LandingLinks
{
    public function __construct(
        private readonly Config $config,
        private readonly Facets $facets,
        private readonly Documents $documents,
    ) {}

    /**
     * @return list<array{name: string, url: string}>
     */
    public function forCategory(Category $category, string $locale): array
    {
        $limit = $this->limit('category');

        if ($limit === 0) {
            return [];
        }

        return $this->links($this->linkable($locale)
            ->where('category_id', $category->getKey())
            ->where('on_category', true)
            ->orderBy('position')
            ->orderBy('id')
            ->limit($limit)
            ->get(), $locale);
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    public function siblings(Landing $landing, string $locale): array
    {
        $limit = $this->limit('siblings');

        if ($limit === 0) {
            return [];
        }

        $query = $this->linkable($locale)->whereKeyNot($landing->getKey());
        $landing->category_id === null ? $query->whereNull('category_id') : $query->where('category_id', $landing->category_id);

        return $this->links($query->orderBy('position')->orderBy('id')->limit($limit)->get(), $locale);
    }

    /**
     * The same set on other bases, in the order of the categories' tree.
     *
     * @return list<array{name: string, url: string}>
     */
    public function elsewhere(Landing $landing, string $locale): array
    {
        $limit = $this->limit('elsewhere');
        $set = $landing->set();
        $first = $set->keys()[0] ?? null;

        if ($limit === 0 || $first === null) {
            return [];
        }

        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string) json_encode($first)).'%';

        $found = $this->linkable($locale)
            ->whereKeyNot($landing->getKey())
            ->whereNotNull('catalog_landings.category_id')
            ->where('catalog_landings.category_id', '!=', $landing->category_id ?? 0)
            ->where('filters', 'like', $needle)
            ->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_landings.category_id')
            ->orderBy('catalog_categories.lft')
            ->select('catalog_landings.*')
            ->get()
            ->filter(static fn (Landing $other): bool => $other->set()->equals($set))
            ->take($limit);

        return $this->links(new Collection($found->values()->all()), $locale);
    }

    /**
     * The landings whose list holds the product: on its categories and their ancestors, or on the
     * whole catalogue, with every facet of the set matched by the product's document. In order of
     * position, the deeper base first.
     *
     * @return list<array{name: string, url: string}>
     */
    public function forProduct(Product $product, string $locale): array
    {
        $limit = $this->limit('product');

        if ($limit === 0) {
            return [];
        }

        $document = $this->documents->build(new Collection([$product]), [$locale])[(int) $product->id] ?? null;

        if ($document === null) {
            return [];
        }

        $categories = array_map('intval', (array) ($document['categories'] ?? []));

        $found = $this->linkable($locale)
            ->with('category')
            ->where(static function (Builder $bases) use ($categories): void {
                $bases->whereNull('category_id');

                if ($categories !== []) {
                    $bases->orWhereIn('category_id', $categories);
                }
            })
            ->get()
            ->filter(fn (Landing $landing): bool => $this->holds($landing, $document))
            ->sortBy([
                static fn (Landing $a, Landing $b): int => $a->position <=> $b->position,
                static fn (Landing $a, Landing $b): int => ($b->category->lft ?? 0) <=> ($a->category->lft ?? 0),
                static fn (Landing $a, Landing $b): int => $a->id <=> $b->id,
            ])
            ->take($limit);

        return $this->links(new Collection($found->values()->all()), $locale);
    }

    /**
     * Whether the product's document is in the landing's list: a list facet by any of its values —
     * the filter's «or» within a facet — and a range by the number.
     *
     * @param  array<string, mixed>  $document
     */
    private function holds(Landing $landing, array $document): bool
    {
        $state = $landing->state();

        if ($state->isEmpty()) {
            return false;
        }

        foreach ($state->all() as $key => $value) {
            $facet = $this->facets->find($key);

            if ($facet === null || ! $this->matches($value, $document[$facet->field()->name] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function matches(FacetValue $value, mixed $held): bool
    {
        if ($value->isRange()) {
            if (! is_numeric($held)) {
                return false;
            }

            return ($value->min === null || (float) $held >= $value->min) && ($value->max === null || (float) $held <= $value->max);
        }

        $held = array_map(static fn (mixed $one): string => is_scalar($one) ? (string) $one : '', is_array($held) ? $held : [$held]);

        return array_intersect($value->values, $held) !== [];
    }

    /**
     * @return Builder<Landing>
     */
    private function linkable(string $locale): Builder
    {
        return Landing::query()
            ->linkable($locale)
            ->with(['routes' => static fn ($routes) => $routes->where('locale', $locale)->where('kind', Route::CANONICAL)]);
    }

    /**
     * @param  Collection<int, Landing>  $landings
     * @return list<array{name: string, url: string}>
     */
    private function links(Collection $landings, string $locale): array
    {
        $links = [];

        foreach ($landings as $landing) {
            $route = $landing->routes->first();

            if ($route instanceof Route) {
                $links[] = ['name' => $landing->label($locale), 'url' => $landing->urlOf($route->path, $locale)];
            }
        }

        return $links;
    }

    private function limit(string $list): int
    {
        return max(0, (int) $this->config->get('webx-catalog-landings.links.'.$list, 0));
    }
}
