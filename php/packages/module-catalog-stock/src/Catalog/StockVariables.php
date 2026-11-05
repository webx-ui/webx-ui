<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Closure;
use Illuminate\Support\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Variables\ComputedVariable;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\Templates\Variable;

/**
 * What a template says about stock (§4.2 of the feeds spec). A file writes nothing for a product on
 * the default status, so that the default can move; a template is told the status it is really in.
 *
 * `availability` here is the default of §6.1, before any feed maps it: the status that can be bought
 * is `in_stock`, the rest `out_of_stock`. A product with no status at all, on a site without a
 * default, is sold as {@see StockRule} sells it.
 */
final class StockVariables
{
    public static function register(ProductVariables $variables): void
    {
        $variables->register(self::variable(StockPart::KEY, 'webx-catalog-stock::product.status', 'in-stock', static fn (?StockStatus $status): string => (string) $status?->code));
        $variables->register(self::variable('stock_name', 'webx-catalog-stock::variables.stock_name', 'In stock', static fn (?StockStatus $status, string $locale): string => (string) $status?->displayName($locale)));
        $variables->register(self::variable('is_purchasable', 'webx-catalog-stock::variables.is_purchasable', '1', static fn (?StockStatus $status): string => self::sold($status) ? '1' : ''));
        $variables->register(self::variable('availability', 'webx-catalog::variables.availability', 'in_stock', static fn (?StockStatus $status): string => self::sold($status) ? 'in_stock' : 'out_of_stock'));
    }

    /**
     * @param  Closure(?StockStatus, string): string  $value
     */
    private static function variable(string $name, string $label, string $example, Closure $value): ComputedVariable
    {
        return new ComputedVariable(new Variable($name, $label, $example), static function (Collection $products, string $locale) use ($value): array {
            $ids = $products->map(static fn (Product $product): int => (int) $product->id)->all();
            $statuses = Stock::of($ids);
            $values = [];

            foreach ($ids as $id) {
                $values[$id] = $value($statuses[$id] ?? null, $locale);
            }

            return $values;
        });
    }

    private static function sold(?StockStatus $status): bool
    {
        return $status === null || $status->is_purchasable;
    }
}
