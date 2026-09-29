<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Catalog\Models\Product;
use WebxUi\Routing\Models\Route;

/**
 * Products, for whatever points at an entity: a menu entry, a link field (§14). Found by name,
 * article number or barcode; the hint is the article number and the main category, which is what
 * tells two "Classic shirt" apart. Available is visible (§5): an unpublished product has a page,
 * but a menu should not lead to one.
 */
final class ProductLinkSource implements LinkSource
{
    public function type(): string
    {
        return Product::TYPE;
    }

    public function model(): string
    {
        return Product::class;
    }

    public function title(): string
    {
        return (string) __('webx-catalog::module.products');
    }

    public function icon(): string
    {
        return 'cart';
    }

    public function order(): int
    {
        return 301;
    }

    public function permission(): string
    {
        return 'catalog.view';
    }

    public function search(string $query, string $locale, int $limit): array
    {
        $products = $this->query($locale)
            ->when($query !== '', static fn (Builder $found): Builder => $found->matching($query))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($products, $locale));
    }

    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Product>
     */
    private function query(string $locale): Builder
    {
        return Product::query()->with([
            'category',
            'routes' => static fn ($routes) => $routes->where('locale', $locale)->where('kind', Route::CANONICAL),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Product>  $products
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $products, string $locale): array
    {
        $visible = $products->isEmpty() ? [] : Product::query()->whereKey($products->modelKeys())->visible()
            ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $candidates = [];

        foreach ($products as $product) {
            $id = (int) $product->getKey();
            $canonical = $product->routes->first();
            $hint = array_filter([$product->sku, $product->category?->displayName($locale)]);

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: $product->displayName($locale),
                url: $canonical instanceof Route ? $product->urlOf($canonical->path, $locale) : null,
                available: $canonical instanceof Route && in_array($id, $visible, true),
                hint: $hint === [] ? null : implode(' · ', $hint),
            );
        }

        return $candidates;
    }
}
