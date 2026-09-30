<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Localization\Locales;

/**
 * The labels of a page of products in one query, and the small conversions every part of this
 * module needs — so the form, the list, the card and the document read labels the same way.
 */
final class Labels
{
    /**
     * The labels on each product, in the order of the reference book.
     *
     * @param  list<int|string>  $productIds
     * @param  (callable(Builder<Label>): void)|null  $narrow  Only badges, only the ones in the filter…
     * @return array<int, list<Label>> product id → labels; a product without any is left out
     */
    public static function on(array $productIds, bool $withTrashed = false, ?callable $narrow = null): array
    {
        if ($productIds === []) {
            return [];
        }

        $query = $withTrashed ? Label::withTrashed() : Label::query();
        $query->join(Label::LINKS, Label::LINKS.'.label_id', '=', 'catalog_labels.id')
            ->whereIn(Label::LINKS.'.product_id', $productIds)
            ->select(['catalog_labels.*', Label::LINKS.'.product_id as linked_product_id'])
            ->orderBy('catalog_labels.position')
            ->orderBy('catalog_labels.id');

        if ($narrow !== null) {
            $narrow($query);
        }

        $on = [];

        foreach ($query->get() as $label) {
            $on[(int) $label->getAttribute('linked_product_id')][] = $label;
        }

        return $on;
    }

    /**
     * Whatever arrived, as the list of ids it was meant to be.
     *
     * @return list<int>
     */
    public static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $one) {
            if (is_int($one) || (is_string($one) && ctype_digit($one))) {
                $ids[(int) $one] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * What the journal shows: names, in the order of the reference book.
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    public static function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $locale = app(Locales::class)->current();

        return Label::withTrashed()->whereKey($ids)->scopes(['ordered'])->get()
            ->map(static fn (Label $label): string => $label->displayName($locale))
            ->values()
            ->all();
    }
}
