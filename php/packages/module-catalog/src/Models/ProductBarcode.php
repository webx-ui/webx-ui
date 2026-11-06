<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One additional barcode of a product (decision 17 of the feeds spec). Written by the product form
 * alone, which also journals the list and marks the product for the index: a row saved anywhere
 * else is a code the search never hears of.
 *
 * @property int $id
 * @property int $product_id
 * @property string $barcode
 * @property int $position
 */
final class ProductBarcode extends Model
{
    /** The length of the column, and of the main barcode. */
    public const LENGTH = 64;

    public $timestamps = false;

    protected $table = 'catalog_product_barcodes';

    /** @var list<string> */
    protected $fillable = ['product_id', 'barcode', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['product_id' => 'integer', 'position' => 'integer'];
    }
}
