<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Closure;
use Illuminate\Support\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Variables\ComputedVariable;
use WebxUi\Catalog\Variables\FirstValues;
use WebxUi\Catalog\Variables\ProductVariable;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * The sources of `custom_label_0…4` (§3.3 of the feeds spec). A source is looked up when its label
 * is asked for: a label the template does not print cannot fail a build.
 */
final class FeedLabels
{
    public const COUNT = 5;

    public const NONE = 'none';

    public const LABEL = 'label';

    public const PRICE_RANGE = 'price_range';

    public const PROPERTY = 'property';

    public const CONSTANT = 'constant';

    public const TYPES = [self::NONE, self::LABEL, self::PRICE_RANGE, self::PROPERTY, self::CONSTANT];

    /** What Google takes in a label. */
    private const MAX_LENGTH = 100;

    /** The variable of the labels satellite: a list of `code` and `name`. */
    private const LABELS = 'labels';

    /**
     * What is wrong with the sources, in words for the form; null when nothing is.
     *
     * @param  array<array-key, mixed>  $labels
     */
    public static function problem(array $labels): ?string
    {
        foreach ($labels as $label => $source) {
            if (! is_int($label) && ! ctype_digit($label) || (int) $label >= self::COUNT) {
                return (string) __('webx-catalog-feeds::errors.label-key', ['label' => $label]);
            }

            $type = is_array($source) ? ($source['type'] ?? null) : null;

            if (! is_array($source) || ! in_array($type, self::TYPES, true)) {
                return (string) __('webx-catalog-feeds::errors.label-type', ['label' => $label]);
            }

            $key = match ($type) {
                self::LABEL => self::codes($source['labels'] ?? null) ? null : 'label-codes',
                self::PRICE_RANGE => self::ranges($source['ranges'] ?? null) ? null : 'label-ranges',
                self::PROPERTY => is_string($source['property'] ?? null) ? null : 'label-no-property',
                self::CONSTANT => is_string($source['value'] ?? null) ? null : 'label-no-value',
                default => null,
            };

            if ($key !== null) {
                return (string) __('webx-catalog-feeds::errors.'.$key, ['label' => $label]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, ProductVariable>  $all
     * @return list<ProductVariable>
     */
    public function variables(Feed $feed, array $all): array
    {
        $variables = [];

        foreach ($feed->custom_labels as $label => $source) {
            $value = $this->source((int) $label, (array) $source, $all);

            if ($value instanceof Closure) {
                $variables[] = new ComputedVariable($all['custom_label_'.$label]->describe(), static fn (Collection $products, string $locale): array => array_map(
                    static fn (mixed $text): string => is_string($text) ? rtrim(mb_substr(trim($text), 0, self::MAX_LENGTH)) : '',
                    $value($products, $locale),
                ));
            }
        }

        return $variables;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, ProductVariable>  $all
     * @return (Closure(Collection<int, Product>, string): array<int, mixed>)|null
     */
    private function source(int $label, array $source, array $all): ?Closure
    {
        return match ($source['type'] ?? self::NONE) {
            self::LABEL => static fn (Collection $products, string $locale): array => self::firstLabel(
                $all[self::LABELS] ?? throw FeedFailure::of('label-source', ['label' => $label]),
                (array) ($source['labels'] ?? []),
                $products,
                $locale,
            ),
            self::PRICE_RANGE => static fn (Collection $products): array => $products->mapWithKeys(
                static fn (Product $product): array => [(int) $product->id => self::range((array) ($source['ranges'] ?? []), (float) $product->price)],
            )->all(),
            self::PROPERTY => static function (Collection $products, string $locale) use ($label, $source, $all): array {
                $code = (string) ($source['property'] ?? '');
                $property = FeedVariables::property($all, $code) ?? throw FeedFailure::of('label-property', ['label' => $label, 'property' => $code]);

                return $property instanceof FirstValues ? $property->firstValues($products, $locale) : $property->values($products, $locale);
            },
            self::CONSTANT => static fn (Collection $products): array => $products->mapWithKeys(
                static fn (Product $product): array => [(int) $product->id => (string) ($source['value'] ?? '')],
            )->all(),
            default => null,
        };
    }

    /**
     * The first label of the source's list that the product has, in the order of the list.
     *
     * @param  array<array-key, mixed>  $wanted
     * @param  Collection<int, Product>  $products
     * @return array<int, string>
     */
    private static function firstLabel(ProductVariable $labels, array $wanted, Collection $products, string $locale): array
    {
        $values = [];

        foreach ($labels->values($products, $locale) as $id => $list) {
            $codes = is_array($list) ? array_column($list, 'code') : [];
            $values[$id] = (string) (array_values(array_intersect($wanted, $codes))[0] ?? '');
        }

        return $values;
    }

    /**
     * The first range the price is in: `to` belongs to its range, and a range without one has no end.
     *
     * @param  array<array-key, mixed>  $ranges
     */
    private static function range(array $ranges, float $price): string
    {
        foreach ($ranges as $range) {
            if (! isset($range['to']) || $price <= (float) $range['to']) {
                return (string) ($range['value'] ?? '');
            }
        }

        return '';
    }

    private static function codes(mixed $codes): bool
    {
        return is_array($codes) && $codes !== [] && array_filter($codes, is_string(...)) === $codes;
    }

    /** Going up, each with a value, and only the last without an end. */
    private static function ranges(mixed $ranges): bool
    {
        if (! is_array($ranges) || ! array_is_list($ranges) || $ranges === []) {
            return false;
        }

        $last = count($ranges) - 1;
        $from = -INF;

        foreach ($ranges as $index => $range) {
            $to = is_array($range) ? ($range['to'] ?? null) : null;

            if (! is_array($range) || ! is_string($range['value'] ?? null)) {
                return false;
            }

            if ($to === null) {
                if ($index !== $last) {
                    return false;
                }

                continue;
            }

            if (! is_numeric($to) || (float) $to <= $from) {
                return false;
            }

            $from = (float) $to;
        }

        return true;
    }
}
