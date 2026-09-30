<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\Catalog\Parts\PartSchema;
use WebxUi\Catalog\Parts\ProductPart;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Localization\Locales;

/**
 * The brand of a product in its form: `brand.id`, one choice (§3 of the dictionaries spec). Empty
 * is no brand, and takes the row away.
 *
 * Any brand out of the bin may be chosen, hidden ones too: hiding a brand takes it off the site,
 * not off the products an editor is still filing under it.
 */
final class BrandPart implements ProductPart
{
    public const KEY = 'brand';

    public function key(): string
    {
        return self::KEY;
    }

    public function describe(): PartSchema
    {
        return new PartSchema('webx-catalog-brands::product.brand', 'catalog-brands', [
            new PartField('id', 'id', 'webx-catalog-brands::product.brand', ['nullable', 'integer', 'exists:catalog_brands,id'], 'catalog_brands_list'),
        ]);
    }

    public function rules(): array
    {
        return ['id' => ['nullable', 'integer', Rule::exists('catalog_brands', 'id')->whereNull('deleted_at')]];
    }

    public function read(Collection $products): array
    {
        $values = [];
        $of = Brands::of($products->modelKeys());

        foreach ($products as $product) {
            $values[(int) $product->id] = ['id' => isset($of[(int) $product->id]) ? $of[(int) $product->id]->id : null];
        }

        return $values;
    }

    public function write(Product $product, array $input): array
    {
        if (! array_key_exists('id', $input)) {
            return [];
        }

        $chosen = $input['id'] === null || $input['id'] === '' ? null : (int) $input['id'];

        return self::put($product, $chosen);
    }

    /**
     * The one way a product's brand changes — the form and the bulk action both — and the line of
     * the journal it makes.
     *
     * @return list<array{field: string, from: mixed, to: mixed, label: string}>
     */
    public static function put(Product $product, ?int $id): array
    {
        $before = Brands::of([$product->id])[(int) $product->id] ?? null;

        if ($before?->id === $id) {
            return [];
        }

        if ($id === null) {
            DB::table(Brand::LINKS)->where('product_id', $product->id)->delete();
        } else {
            DB::table(Brand::LINKS)->updateOrInsert(['product_id' => $product->id], ['brand_id' => $id]);
        }

        $locale = app(Locales::class)->current();

        return [[
            'field' => self::KEY.'.id',
            'from' => $before?->displayName($locale),
            'to' => $id === null ? null : Brand::withTrashed()->find($id)?->displayName($locale),
            'label' => (string) __('webx-catalog-brands::product.brand'),
        ]];
    }
}
