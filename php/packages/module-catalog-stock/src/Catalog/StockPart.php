<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\Catalog\Parts\PartSchema;
use WebxUi\Catalog\Parts\ProductPart;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\Localization\Locales;

/**
 * The stock status of a product in its form: `stock.status`, one choice (§3 of the dictionaries
 * spec). A product without a row reads as the default status.
 *
 * A save writes the row even when the status chosen is the default: explicit beats implicit, and
 * moving the default later must not quietly move the products somebody set by hand (§2.2). Only an
 * empty value takes the row away — "whatever the default is".
 */
final class StockPart implements ProductPart
{
    public const KEY = 'stock';

    public function key(): string
    {
        return self::KEY;
    }

    public function describe(): PartSchema
    {
        return new PartSchema('webx-catalog-stock::module.title', 'catalog-stock', [
            new PartField('status', 'id', 'webx-catalog-stock::product.status', ['nullable', 'integer', 'exists:catalog_stock_statuses,id'], 'catalog_stock_list'),
        ]);
    }

    public function rules(): array
    {
        return ['status' => ['nullable', 'integer', Rule::exists('catalog_stock_statuses', 'id')->whereNull('deleted_at')]];
    }

    public function read(Collection $products): array
    {
        $values = [];
        $of = Stock::of($products->modelKeys());

        foreach ($products as $product) {
            $values[(int) $product->id] = ['status' => isset($of[(int) $product->id]) ? $of[(int) $product->id]->id : null];
        }

        return $values;
    }

    public function write(Product $product, array $input): array
    {
        if (! array_key_exists('status', $input)) {
            return [];
        }

        $before = Stock::of([$product->id])[(int) $product->id] ?? null;
        $row = DB::table(StockStatus::LINKS)->where('product_id', $product->id);
        $chosen = $input['status'];

        if ($chosen === null || $chosen === '') {
            $row->delete();
        } else {
            DB::table(StockStatus::LINKS)->updateOrInsert(['product_id' => $product->id], ['status_id' => (int) $chosen]);
        }

        $after = Stock::of([$product->id])[(int) $product->id] ?? null;

        if ($before?->id === $after?->id) {
            return [];
        }

        $locale = app(Locales::class)->current();

        return [[
            'field' => self::KEY.'.status',
            'from' => $before?->displayName($locale),
            'to' => $after?->displayName($locale),
            'label' => (string) __('webx-catalog-stock::product.status'),
        ]];
    }
}
