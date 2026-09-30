<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use WebxUi\Catalog\Bulk\BulkAction;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;
use WebxUi\Localization\Locales;

/**
 * Put every product chosen into one stock status (§3 of the dictionaries spec). The row is
 * written even for the default status, as the form writes it; a product already in the status by
 * its own row changes nothing.
 */
final class StockAction implements BulkAction
{
    public function key(): string
    {
        return 'set-stock';
    }

    public function label(): string
    {
        return (string) __('webx-catalog-stock::bulk.set-stock');
    }

    public function permission(): string
    {
        return 'catalog.manage';
    }

    public function trashed(): bool
    {
        return false;
    }

    public function params(): array
    {
        return [new PartField('status_id', 'id', 'webx-catalog-stock::product.status', ['required', 'integer'], 'catalog_stock_list', StockServiceProvider::SOURCE)];
    }

    public function rules(): array
    {
        return ['status_id' => ['required', 'integer', Rule::exists('catalog_stock_statuses', 'id')->whereNull('deleted_at')]];
    }

    public function apply(Product $product, array $params): array
    {
        $id = (int) $params['status_id'];
        $row = DB::table(StockStatus::LINKS)->where('product_id', $product->id)->value('status_id');

        if ($row !== null && (int) $row === $id) {
            return [];
        }

        $before = Stock::of([$product->id])[(int) $product->id] ?? null;
        DB::table(StockStatus::LINKS)->updateOrInsert(['product_id' => $product->id], ['status_id' => $id]);

        if ($before?->id === $id) {
            // Written down, not changed: the product was in the default status without a row.
            return [];
        }

        $locale = app(Locales::class)->current();

        return [[
            'field' => StockPart::KEY.'.status',
            'from' => $before?->displayName($locale),
            'to' => StockStatus::withTrashed()->find($id)?->displayName($locale),
            'label' => (string) __('webx-catalog-stock::product.status'),
        ]];
    }
}
