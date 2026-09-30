<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Validation\Rule;
use WebxUi\Catalog\Bulk\BulkAction;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\CatalogBrands\BrandsServiceProvider;

/**
 * Give every product chosen one brand — or none (§3 of the dictionaries spec). One action for
 * both, as the form's field is one field: an empty brand takes the brand away, and a product
 * already of the brand changes nothing.
 */
final class BrandAction implements BulkAction
{
    public function key(): string
    {
        return 'set-brand';
    }

    public function label(): string
    {
        return (string) __('webx-catalog-brands::bulk.set-brand');
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
        return [new PartField('brand_id', 'id', 'webx-catalog-brands::product.brand', ['nullable', 'integer'], 'catalog_brands_list', BrandsServiceProvider::SOURCE)];
    }

    public function rules(): array
    {
        return ['brand_id' => ['nullable', 'integer', Rule::exists('catalog_brands', 'id')->whereNull('deleted_at')]];
    }

    public function apply(Product $product, array $params): array
    {
        $id = $params['brand_id'] ?? null;

        return BrandPart::put($product, $id === null || $id === '' ? null : (int) $id);
    }
}
