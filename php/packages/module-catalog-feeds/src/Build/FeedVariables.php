<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Variables\ComputedVariable;
use WebxUi\Catalog\Variables\IdentifierExists;
use WebxUi\Catalog\Variables\ProductVariable;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\Templates\Template;

/**
 * What the settings of one feed change in the registry's answer (§3.3, §3.4, §6 of the feeds spec),
 * and which variables a template really asks for (§7.1).
 */
final readonly class FeedVariables
{
    /** The status of a product, in the words of the stock satellite. */
    private const STOCK = 'stock';

    private const IN_STOCK = 'in_stock';

    public function __construct(
        private ProductVariables $variables,
        private FeedCategories $categories,
        private FeedLabels $labels,
    ) {}

    /**
     * A property by the code a person wrote: a variable writes `-` as `_`.
     *
     * @param  array<string, ProductVariable>  $all
     */
    public static function property(array $all, string $code): ?ProductVariable
    {
        return $all['property.'.str_replace('-', '_', $code)] ?? null;
    }

    /**
     * The names to compute for a template: a field of a list is the list's, not a variable.
     *
     * @return list<string>
     */
    public function used(string $template): array
    {
        $all = $this->variables->all();
        $names = [];

        foreach (Template::usages($template) as $usage) {
            foreach ($usage['sections'] as $section) {
                if (isset($all[$section]) && array_key_exists($usage['name'], $all[$section]->describe()->fields)) {
                    continue 2;
                }
            }

            $names[$usage['name']] = true;
        }

        return array_keys($names);
    }

    /**
     * Asked once a page: a family of the registry keeps what it read of the page it was made for.
     *
     * @return list<ProductVariable>
     *
     * @throws FeedFailure
     */
    public function overrides(Feed $feed): array
    {
        $all = $this->variables->all();

        return [
            $this->availability($feed, $all),
            ...$this->mpn($feed, $all),
            ...$this->platformCategory($feed, $all),
            ...$this->labels->variables($feed, $all),
        ];
    }

    /**
     * The two names of one value, read once between them, and not at all by a template without either.
     *
     * @param  array<string, ProductVariable>  $all
     * @return list<ProductVariable>
     */
    private function platformCategory(Feed $feed, array $all): array
    {
        $map = null;

        $values = function (Collection $products) use ($feed, &$map): array {
            $map ??= $this->categories->map($feed);
            $found = $map;

            return $products->mapWithKeys(static fn (Product $product): array => [
                (int) $product->id => $found->value($product->category_id === null ? null : (int) $product->category_id),
            ])->all();
        };

        return [
            new ComputedVariable($all['platform_category']->describe(), $values),
            new ComputedVariable($all['google_product_category']->describe(), $values),
        ];
    }

    /**
     * @param  array<string, ProductVariable>  $all
     */
    private function availability(Feed $feed, array $all): ProductVariable
    {
        $default = (string) ($feed->options['default_availability'] ?? self::IN_STOCK);
        $map = $feed->stock_map;
        $stock = $all[self::STOCK] ?? null;
        $own = $all['availability'];

        return new ComputedVariable($own->describe(), static function (Collection $products, string $locale) use ($default, $map, $stock, $own): array {
            if ($stock === null) {
                return $products->mapWithKeys(static fn (Product $product): array => [(int) $product->id => $default])->all();
            }

            $codes = $stock->values($products, $locale);
            $values = $own->values($products, $locale);

            foreach ($values as $id => $value) {
                $code = $codes[$id] ?? null;
                $values[$id] = is_string($code) && is_string($map[$code] ?? null) ? $map[$code] : $value;
            }

            return $values;
        });
    }

    /**
     * `mpn` from a property, and `identifier_exists` over it, or the two would disagree.
     *
     * @param  array<string, ProductVariable>  $all
     * @return list<ProductVariable>
     */
    private function mpn(Feed $feed, array $all): array
    {
        $source = (array) ($feed->sources['mpn'] ?? []);

        if (($source['type'] ?? 'field') !== 'property') {
            return [];
        }

        $code = (string) ($source['property'] ?? '');
        $property = self::property($all, $code) ?? throw FeedFailure::of('source-property', ['property' => $code]);
        $mpn = new ComputedVariable($all['mpn']->describe(), $property->values(...));

        return [$mpn, new IdentifierExists($mpn)];
    }
}
