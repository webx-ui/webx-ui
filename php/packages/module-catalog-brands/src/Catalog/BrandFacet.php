<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\AbstractFacet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Facets\OrderedFacet;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogBrands\Models\Brand;

/**
 * The brand as a filter (§2.3, §3 of the dictionaries spec): terms, `brand_apple` in the address,
 * indexable — «Laptops Apple» is a first level open to search engines (decision 17 of the
 * architecture). The value is the brand's id; its slug in the address is the brand's own slug in
 * the page's language, the same word as its page.
 *
 * A hidden brand is no value: not counted, not resolved from an address. Values stand in the
 * order of the list of brands, not the alphabet.
 */
final class BrandFacet extends AbstractFacet implements OrderedFacet
{
    public const KEY = 'brand';

    public function key(): string
    {
        return self::KEY;
    }

    public function code(): string
    {
        return self::KEY;
    }

    public function kind(): FacetKind
    {
        return FacetKind::Terms;
    }

    public function label(): string
    {
        return (string) __('webx-catalog-brands::product.brand');
    }

    public function indexable(): bool
    {
        return true;
    }

    public function field(): IndexField
    {
        return new IndexField('brand', IndexField::INT);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        $labels = [];

        foreach ($this->load($values) as $brand) {
            $labels[(string) $brand->id] = $brand->displayName($locale);
        }

        return $labels;
    }

    /**
     * The slug in the page's language; a brand without one there is spelled by its id, which
     * {@see resolveSlugs()} reads back.
     *
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function slugs(array $values, string $locale): array
    {
        $slugs = [];

        foreach ($this->load($values) as $brand) {
            $slug = $brand->getTranslation('slug', $locale, false);
            $slugs[(string) $brand->id] = is_string($slug) && $slug !== '' ? $slug : (string) $brand->id;
        }

        return $slugs;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        if ($slugs === []) {
            return [];
        }

        $found = Brand::query()->visible()
            ->where(static function (Builder $any) use ($slugs, $locale): void {
                foreach ($slugs as $slug) {
                    $any->orWhere(static fn (Builder $one): Builder => $one->whereTranslation('slug', $slug, $locale));
                }
            })
            ->get();

        $resolved = [];

        foreach ($found as $brand) {
            $slug = $brand->getTranslation('slug', $locale, false);

            if (is_string($slug) && in_array($slug, $slugs, true)) {
                $resolved[$slug] = (string) $brand->id;
            }
        }

        // A brand without a slug in this language, spelled by its id.
        $ids = array_values(array_filter($slugs, static fn (string $slug): bool => ! isset($resolved[$slug]) && ctype_digit($slug)));

        foreach ($ids === [] ? [] : Brand::query()->visible()->whereKey(array_map('intval', $ids))->pluck('id') as $id) {
            $resolved[(string) $id] = (string) $id;
        }

        return $resolved;
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void
    {
        $ids = Brand::query()->visible()->whereKey($this->ids($value->values))->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        $query->whereIn($query->getModel()->qualifyColumn('id'), $query->getQuery()->newQuery()
            ->from(Brand::LINKS)
            ->select('product_id')
            ->whereIn('brand_id', $ids === [] ? [0] : array_values($ids)));
    }

    public function sqlValues(QueryBuilder $products): QueryBuilder
    {
        return $products->newQuery()
            ->from(Brand::LINKS.' as branded')
            ->join('catalog_brands as brands', 'brands.id', '=', 'branded.brand_id')
            ->whereIn('branded.product_id', $products)
            ->where('brands.is_visible', true)
            ->whereNull('brands.deleted_at')
            ->select(['branded.product_id as product_id', 'brands.id as value']);
    }

    /**
     * @param  list<string>  $values
     * @return Collection<int, Brand>
     */
    private function load(array $values): Collection
    {
        return Brand::query()->visible()->whereKey($this->ids($values))->scopes(['ordered'])->get();
    }

    /**
     * @param  list<string>  $values
     * @return list<int>
     */
    private function ids(array $values): array
    {
        return array_values(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0));
    }
}
