<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use WebxUi\Catalog\Facets\AbstractFacet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Facets\OrderedFacet;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogStock\Models\StockStatus;

/**
 * The stock status as a filter (§3 of the dictionaries spec): terms, `stock_in-stock` in the
 * address, not indexable. A product without a row counts under the default status, in SQL rather
 * than in a loop — a left join and a `coalesce`.
 *
 * A status out of the filter (`is_visible` off) is no value: not counted, not resolved from an
 * address.
 */
final class StockFacet extends AbstractFacet implements OrderedFacet
{
    public const KEY = 'stock';

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
        return (string) __('webx-catalog-stock::product.status');
    }

    public function indexable(): bool
    {
        return false;
    }

    public function field(): IndexField
    {
        return new IndexField('stock', IndexField::INT);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        $labels = [];

        foreach ($this->load($values) as $status) {
            $labels[(string) $status->id] = $status->displayName($locale);
        }

        return $labels;
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function slugs(array $values, string $locale): array
    {
        $slugs = [];

        foreach ($this->load($values) as $status) {
            $slugs[(string) $status->id] = $status->code;
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

        return StockStatus::query()->where('is_visible', true)->whereIn('code', $slugs)->pluck('id', 'code')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void
    {
        $ids = StockStatus::query()->where('is_visible', true)->whereKey($this->ids($value->values))->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        Stock::whereIn($query, array_values($ids));
    }

    public function sqlValues(QueryBuilder $products): QueryBuilder
    {
        $default = StockStatus::query()->where('is_default', true)->value('id');

        return $products->newQuery()
            ->from('catalog_products as stocked')
            ->leftJoin(StockStatus::LINKS.' as rows', 'rows.product_id', '=', 'stocked.id')
            ->join('catalog_stock_statuses as statuses', static function (JoinClause $join) use ($default): void {
                $join->on('statuses.id', '=', 'rows.status_id')
                    ->orWhere(static function (JoinClause $fallback) use ($default): void {
                        $fallback->whereNull('rows.status_id')->where('statuses.id', '=', is_numeric($default) ? (int) $default : 0);
                    });
            })
            ->whereIn('stocked.id', $products)
            ->where('statuses.is_visible', true)
            ->whereNull('statuses.deleted_at')
            ->select(['stocked.id as product_id', 'statuses.id as value']);
    }

    /**
     * @param  list<string>  $values
     * @return Collection<int, StockStatus>
     */
    private function load(array $values): Collection
    {
        return StockStatus::query()->where('is_visible', true)->whereKey($this->ids($values))->scopes(['ordered'])->get();
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
