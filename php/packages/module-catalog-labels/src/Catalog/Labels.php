<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\StorefrontCache;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Localization\Locales;

/**
 * The labels of a page of products in one query, and the small conversions every part of this
 * module needs — so the form, the list, the card and the document read labels the same way.
 *
 * On the storefront it is also the seam a template reads labels through: `app(Labels::class)->of($product)`
 * is every label on the product — the badges and the service ones — out of what the page loaded
 * for all its products at once ({@see load()}), so a theme never queries the labels itself.
 */
final class Labels
{
    /** What this request loaded: product id → its labels; a product without any is there as []. */
    private const MEMO = 'labels.on';

    public function __construct(private readonly StorefrontCache $cache) {}

    /**
     * Every label on the product, badges and service ones, in the order of the reference book.
     * A product of the page costs nothing: the badges of its card loaded it with the rest
     * (`StorefrontParts::prepare()` / `extend()`). Any other one is loaded by itself.
     *
     * @return list<Label>
     */
    public function of(Product|int $product): array
    {
        $id = $product instanceof Product ? (int) $product->getKey() : $product;

        return $this->load([$id])[$id] ?? [];
    }

    /**
     * The labels on these products for the storefront: the ones out of the bin, all of them — a part that wants only the badges filters `is_badge` itself. One query for the
     * products this request has not loaded yet, none for the ones it has, and none while the
     * generation of the catalogue stands: the answer is kept in the storefront's cache under it.
     *
     * Labels have no dates of their own (a «sale until Friday» is someone switching it off), so
     * nothing here expires before the generation moves.
     *
     * @param  list<int|string>  $productIds
     * @return array<int, list<Label>> every product asked, one without labels as []
     */
    public function load(array $productIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        /** @var array<int, list<Label>> $known */
        $known = RequestMemo::remember(self::MEMO, static fn (): array => []);
        $missing = array_values(array_diff($ids, array_keys($known)));

        if ($missing !== []) {
            sort($missing);
            // Plain rows rather than models: what a store keeps should not depend on the class.
            $rows = $this->cache->remember('labels', [$missing], static fn (): array => self::rows($missing));
            $labels = [];

            foreach (Label::hydrate(array_values($rows['labels'])) as $label) {
                $labels[(int) $label->getKey()] = $label;
            }

            foreach ($missing as $id) {
                $known[$id] = array_map(static fn (int $label): Label => $labels[$label], $rows['on'][$id] ?? []);
            }

            RequestMemo::put(self::MEMO, $known);
        }

        $found = [];

        foreach ($ids as $id) {
            $found[$id] = $known[$id];
        }

        return $found;
    }

    /** Forget what this request loaded — after a write that changed which labels a product has. */
    public static function forgetLoaded(): void
    {
        RequestMemo::forget(self::MEMO);
    }

    /**
     * What the storefront's cache keeps: each label once, and the ids on each product in order.
     *
     * @param  list<int>  $productIds
     * @return array{labels: array<int, array<string, mixed>>, on: array<int, list<int>>}
     */
    private static function rows(array $productIds): array
    {
        $labels = [];
        $on = [];

        foreach (self::on($productIds) as $id => $list) {
            foreach ($list as $label) {
                $labels[(int) $label->getKey()] ??= array_diff_key($label->getAttributes(), ['linked_product_id' => true]);
                $on[$id][] = (int) $label->getKey();
            }
        }

        return ['labels' => $labels, 'on' => $on];
    }

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
