<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\AbstractFacet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Facets\OrderedFacet;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogLabels\Models\Label;

/**
 * Labels as a filter (§3 of the dictionaries spec): terms, several at once, `label_sale` in the
 * address — the code, the same in every language.
 *
 * Values are ids, as everywhere in the engine; the code is only the address's. Not indexable: "Sale
 * laptops" is a campaign, not a page a search engine should keep. A label out of the filter
 * (`is_visible` off) is not a value at all — not counted, not resolved from an address — so a
 * service label cannot be reached by typing its code.
 */
final class LabelFacet extends AbstractFacet implements OrderedFacet
{
    public const KEY = 'label';

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
        return (string) __('webx-catalog-labels::product.labels');
    }

    public function indexable(): bool
    {
        return false;
    }

    public function field(): IndexField
    {
        return new IndexField('labels', IndexField::INT, multi: true);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        $labels = [];

        foreach ($this->load($values) as $label) {
            $labels[(string) $label->id] = $label->displayName($locale);
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

        foreach ($this->load($values) as $label) {
            $slugs[(string) $label->id] = $label->code;
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

        return Label::query()->where('is_visible', true)->whereIn('code', $slugs)->pluck('id', 'code')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void
    {
        $query->whereIn($query->getModel()->qualifyColumn('id'), $query->getQuery()->newQuery()
            ->from(Label::LINKS)
            ->select('product_id')
            ->whereIn('label_id', $this->liveIds($value->values)));
    }

    public function sqlValues(QueryBuilder $products): QueryBuilder
    {
        return $products->newQuery()
            ->from(Label::LINKS.' as linked')
            ->join('catalog_labels as labels', 'labels.id', '=', 'linked.label_id')
            ->whereIn('linked.product_id', $products)
            ->where('labels.is_visible', true)
            ->whereNull('labels.deleted_at')
            ->select(['linked.product_id', 'linked.label_id as value']);
    }

    /**
     * The ones in the filter, in the order of the reference book.
     *
     * @param  list<string>  $values
     * @return Collection<int, Label>
     */
    private function load(array $values): Collection
    {
        return Label::query()->where('is_visible', true)->whereKey($this->ids($values))->scopes(['ordered'])->get();
    }

    /**
     * @param  list<string>  $values
     * @return list<int>
     */
    private function liveIds(array $values): array
    {
        $ids = Label::query()->where('is_visible', true)->whereKey($this->ids($values))->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        return $ids === [] ? [0] : array_values($ids);
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
