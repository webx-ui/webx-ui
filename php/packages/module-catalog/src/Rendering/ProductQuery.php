<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Rendering;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\Catalog\Storefront\CategoryIndex;
use WebxUi\Routing\RouteCache;

/**
 * `products()` — the products a template may show, as cards rather than models (§14).
 *
 *     products()->category('shoes')->sort('popular')->take(8)   // a shelf of the home page
 *     products()->only([12, 7, 30])                              // an editor's picks, in order
 *     products()->except($product)->category($product->category_id)->take(4)
 *
 * A category includes everything under it, as the main or an additional one — the same shelf the
 * storefront shows. The sort is a key of the `Sorts` registry, a satellite's included; unknown is
 * the default order. What a reader may see is not a step: visible (§5) and with an address in the
 * language being read.
 *
 * A satellite adds its own steps as macros over {@see narrowedBy()} — `products()->label('sale')`,
 * `products()->inStock()` — so the helper stays one class and the core knows nothing of labels.
 *
 * @extends RecordQuery<Product>
 */
final class ProductQuery extends RecordQuery
{
    use Macroable;

    /**
     * Only what is filed under these categories or below them: an id, a slug, a category, or a
     * list. Nothing is no filter; a slug nobody has matches no product rather than all of them.
     *
     * @param  int|string|Category|iterable<int|string|Category>|null  $categories
     */
    public function category(int|string|Category|iterable|null $categories): self
    {
        $given = [];

        foreach (is_iterable($categories) ? $categories : [$categories] as $category) {
            if ($category instanceof Category) {
                $given[] = (int) $category->getKey();
            } elseif (is_int($category) || (is_string($category) && ctype_digit($category))) {
                $given[] = (int) $category;
            } elseif (is_string($category) && trim($category) !== '') {
                $given[] = trim($category);
            }
        }

        return $this->withStep('category', $given === [] ? null : $given);
    }

    /**
     * A copy narrowed by a satellite's step, under a name of its own: the same name again
     * replaces the step rather than adding a second one, so `label('sale')->label('new')` is the
     * last word, as every other step is.
     *
     * @param  Closure(Builder<covariant Product>, string): void  $narrow  The query, and the language read in.
     */
    public function narrowedBy(string $name, Closure $narrow): self
    {
        /** @var array<string, Closure(Builder<covariant Product>, string): void> $steps */
        $steps = $this->step('narrowed', []);
        $steps[$name] = $narrow;

        return $this->withStep('narrowed', $steps);
    }

    /** A key of the `Sorts` registry: `popular`, `new`, `price_asc`… */
    public function sort(string $key): self
    {
        return $this->withStep('sort', $key);
    }

    protected function newQuery(string $locale): Builder
    {
        // Only the product's own columns: a sort by popularity joins a table of its own. The main
        // category is not eager-loaded: {@see models()} takes it from the request's pool. Nor are
        // the registry rows: noted as the rows arrive, they are read with the page's other
        // addresses by the first one asked — `shownIn()` — and none on a warm page.
        $query = Product::query()->select('catalog_products.*')->visible()->with(['categories', 'images'])
            ->afterQuery(static fn (iterable $products) => Container::getInstance()->make(RouteCache::class)->preload($products));
        $key = $this->step('sort');

        Container::getInstance()->make(Sorts::class)->resolve(is_string($key) ? $key : null)->applySql($query, $locale);

        return $query;
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        return $record instanceof Product && $record->hasUrlIn($locale);
    }

    protected function narrow(Builder $query, string $locale): void
    {
        /** @var array<string, Closure(Builder<covariant Product>, string): void> $steps */
        $steps = $this->step('narrowed', []);

        foreach ($steps as $narrow) {
            $narrow($query, $locale);
        }

        /** @var list<int|string>|null $given */
        $given = $this->step('category');

        if ($given === null) {
            return;
        }

        $ids = [];
        $slugs = [];

        foreach ($given as $category) {
            is_int($category) ? $ids[] = $category : $slugs[] = $category;
        }

        if ($slugs !== []) {
            $ids = [...$ids, ...Category::query()
                ->where(static function (Builder $found) use ($slugs, $locale): void {
                    foreach ($slugs as $slug) {
                        $found->orWhere('slug->'.$locale, $slug);
                    }
                })
                ->pluck('id')->map(intval(...))->all()];
        }

        // Out of the shape of the tree the storefront keeps (§8.4): a shelf of the product's
        // category on its page asks nothing of the tree.
        $subtree = $ids === [] ? [] : Container::getInstance()->make(CategoryIndex::class)->subtreeIds($ids);

        $query->scopes(['inCategories' => [$subtree === [] ? [0] : $subtree]]);
    }

    /**
     * The records, with the main category of each from the request's pool — the one the page's
     * own product and the other shelves filled — rather than a query a shelf.
     *
     * @return EloquentCollection<int, Product>
     */
    public function models(): EloquentCollection
    {
        $records = parent::models();
        Container::getInstance()->make(CategoryIndex::class)->attach($records);

        return $records;
    }

    /** The order is the sort's, put on in {@see newQuery()}; a category has no order of its own. */
    protected function order(Builder $query, ?int $category): void {}

    protected function cards(array $records, string $locale): array
    {
        return Container::getInstance()->make(Cards::class)->products($records, $locale);
    }
}
