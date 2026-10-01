<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Exchange\DescribesCell;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\Lookup;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogStock\Models\StockStatus;

/**
 * The stock status of a product in an exchange file (§7.2 of the exchange spec): `stock`, the code
 * of a status, into `stock.status`.
 *
 * Never created, not even with `create_missing` (decision 8): a status carries «can be bought»,
 * and that is a person's choice, not a supplier's spelling. An unknown code is an error of the row
 * that lists the codes there are.
 *
 * The export writes the status a product was given, and nothing for one that has the default
 * because nobody chose: the empty cell reads back as "leave it", so a file sent back does not pin
 * forty thousand products to today's default, and moving the default later still moves them.
 */
final class StockExchangeColumn implements DescribesCell, ExchangeColumn
{
    private const BAG = 'stock';

    public function key(): string
    {
        return StockPart::KEY;
    }

    public function label(): string
    {
        return (string) __('webx-catalog-stock::product.status');
    }

    public function field(): string
    {
        return StockPart::KEY.'.status';
    }

    public function localized(): bool
    {
        return false;
    }

    public function cellFormat(): string
    {
        return 'The code of a stock status. Never created, not even with create_missing; empty — leave it as it is.';
    }

    public function export(Collection $products, ?string $locale): array
    {
        $ids = $products->map(static fn (Product $product): int => (int) $product->id)->all();
        $codes = DB::table(StockStatus::LINKS.' as chosen')
            ->join('catalog_stock_statuses as statuses', 'statuses.id', '=', 'chosen.status_id')
            ->whereIn('chosen.product_id', $ids)
            ->pluck('statuses.code', 'chosen.product_id');
        $cells = [];

        foreach ($ids as $id) {
            $cells[$id] = (string) ($codes[$id] ?? '');
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        // The live ones: the form takes no status from the bin.
        $book = Lookup::of($context, self::BAG, static fn (): array => StockStatus::query()->orderBy('position')->pluck('id', 'code')
            ->mapWithKeys(static fn (mixed $id, mixed $code): array => [mb_strtolower((string) $code) => (int) $id])
            ->all());
        $code = mb_strtolower(trim($cell));

        return $book->find($code) ?? throw new RowError((string) __('webx-catalog-stock::exchange.unknown', [
            'code' => $code,
            'known' => implode(', ', $book->codes()),
        ]));
    }
}
