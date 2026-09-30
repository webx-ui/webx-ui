<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Catalog\Dictionaries\Dictionary;
use WebxUi\Catalog\Models\Product;

/**
 * A label: top, sale, new — several on a product (§2.1 of the dictionaries spec). A badge on the
 * card when `is_badge`, a value of the filter when `is_visible`; a label with neither is a
 * service one ("for the newsletter") that only a template or an agent picks products by.
 *
 * @property bool $is_badge
 */
class Label extends Dictionary
{
    /** The screen a label is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'catalog.label-form';

    /** The link table: which labels are on which product. */
    public const LINKS = 'catalog_label_product';

    protected $table = 'catalog_labels';

    /** @var list<string> */
    protected $fillable = ['title', 'code', 'color', 'is_visible', 'is_badge', 'position'];

    /** @var array<string, string> */
    protected $casts = ['is_badge' => 'boolean'];

    /**
     * Read by whoever may open the catalogue — the product form files into them — and written by
     * whoever may edit it: the people who put products on sale put the «Sale» on them (decision 8).
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['catalog.view', 'catalog.manage'],
            manage: 'catalog.manage',
            noun: 'label',
            plural: 'labels',
            items: 'products',
        );
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, self::LINKS, 'label_id', 'product_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->products();
    }

    /**
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return [...parent::categoryFields(), 'is_badge'];
    }

    /**
     * @return list<string>
     */
    public function touchingFields(): array
    {
        return [...parent::touchingFields(), 'is_badge'];
    }

    /**
     * @return Builder<Product>
     */
    public function affectedProducts(): Builder
    {
        return Product::withTrashed()->whereIn(
            'catalog_products.id',
            DB::table(self::LINKS)->select('product_id')->where('label_id', $this->getKey()),
        );
    }

    /** A label still on products: deleting it would take it off them without a word. */
    public function inUseMessage(int $count): string
    {
        return (string) __('webx-catalog-labels::errors.in-use', ['count' => $count]);
    }
}
