<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Dictionaries\Dictionary;
use WebxUi\Catalog\Models\Product;

/**
 * A stock status: in stock, out of stock, on order — one per product (§2.2 of the dictionaries
 * spec). Whether it can be bought is the status's, and the chain of `Purchasability` asks it.
 *
 * Exactly one status is the default, and a product without a row in `catalog_product_stock` is in
 * it: the satellite goes onto a live catalogue without writing a row per product. So the default
 * cannot be deleted, and it stops being the default only by another one becoming it — never by
 * being switched off, which would leave every product without a row nowhere.
 *
 * @property bool $is_purchasable
 * @property bool $is_default
 */
class StockStatus extends Dictionary
{
    /** The screen a status is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'catalog.stock-status-form';

    /** Which status each product is in; a product without a row is in the default one. */
    public const LINKS = 'catalog_product_stock';

    protected $table = 'catalog_stock_statuses';

    /** @var list<string> */
    protected $fillable = ['title', 'code', 'color', 'is_visible', 'is_purchasable', 'is_default', 'position'];

    /** @var array<string, string> */
    protected $casts = ['is_purchasable' => 'boolean', 'is_default' => 'boolean'];

    protected static function booted(): void
    {
        parent::booted();

        static::saving(static function (StockStatus $status): void {
            if ($status->exists && ! $status->is_default && (bool) $status->getRawOriginal('is_default')) {
                throw ValidationException::withMessages([
                    'is_default' => [(string) __('webx-catalog-stock::errors.default-off')],
                ]);
            }
        });

        static::saved(static function (StockStatus $status): void {
            // One default: the one just made it takes the flag from the others, in one statement.
            if ($status->is_default && ($status->wasRecentlyCreated || $status->wasChanged('is_default'))) {
                static::withTrashed()->whereKeyNot($status->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
        });

        static::created(static function (StockStatus $status): void {
            // A new default moves every product without a row into itself.
            if ($status->is_default) {
                Container::getInstance()->make(Catalog::class)->touchQuery($status->affectedProducts());
            }
        });

        static::deleting(static function (StockStatus $status): void {
            if ($status->is_default && ! $status->isForceDeleting()) {
                throw new CategoryException((string) __('webx-catalog-stock::errors.default-delete'));
            }
        });
    }

    /**
     * Read by whoever may open the catalogue — the product form files into them — and written by
     * whoever may edit it (decision 8).
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['catalog.view', 'catalog.manage'],
            manage: 'catalog.manage',
            noun: 'status',
            plural: 'statuses',
            items: 'products',
        );
    }

    /** The status a product without a row is in; null only on a site that deleted them all. */
    public static function fallback(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * The products with a row saying they are in this status — not the ones in it by default.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, self::LINKS, 'status_id', 'product_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->products();
    }

    /**
     * The number beside a status counts what the facet counts: the default one holds the products
     * without a row too (§2.2), and "Show products" lands on all of them.
     */
    public function itemCount(): int
    {
        $rows = $this->products()->count();

        return $this->is_default ? $rows + Product::query()->whereNotExists(self::rowOf(...))->count() : $rows;
    }

    /**
     * The same number for every row of the list, as one subquery.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function scopeWithItemCount(Builder $query): Builder
    {
        $table = $this->getTable();

        $count = Product::query()->toBase()->selectRaw('count(*)')->where(static function (QueryBuilder $product) use ($table): void {
            $product->whereExists(static function (QueryBuilder $row) use ($table): void {
                self::rowOf($row);
                $row->whereColumn(self::LINKS.'.status_id', $table.'.id');
            })->orWhere(static function (QueryBuilder $unlisted) use ($table): void {
                $unlisted->where($table.'.is_default', true)->whereNotExists(self::rowOf(...));
            });
        });

        if ($query->getQuery()->columns === null) {
            $query->select($table.'.*');
        }

        return $query->selectSub($count, static::categoryKind()->countKey());
    }

    /** The product's row in the link table. */
    private static function rowOf(QueryBuilder $row): void
    {
        $row->from(self::LINKS)->whereColumn(self::LINKS.'.product_id', 'catalog_products.id');
    }

    /**
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return [...parent::categoryFields(), 'is_purchasable', 'is_default'];
    }

    /**
     * @return list<string>
     */
    public function touchingFields(): array
    {
        return [...parent::touchingFields(), 'is_purchasable', 'is_default'];
    }

    /**
     * Its products — and, when it is or just was the default, every product without a row, whose
     * status that is. One query either way.
     *
     * @return Builder<Product>
     */
    public function affectedProducts(): Builder
    {
        $id = $this->getKey();
        $default = $this->is_default || (bool) $this->getOriginal('is_default') || $this->wasChanged('is_default');

        return Product::withTrashed()->where(static function (Builder $query) use ($id, $default): void {
            $query->whereIn('catalog_products.id', DB::table(self::LINKS)->select('product_id')->where('status_id', $id));

            if ($default) {
                $query->orWhereNotExists(static function (QueryBuilder $row): void {
                    $row->from(self::LINKS)->whereColumn(self::LINKS.'.product_id', 'catalog_products.id');
                });
            }
        });
    }

    /** A status products are in: deleting it would leave them in none. */
    public function inUseMessage(int $count): string
    {
        return (string) __('webx-catalog-stock::errors.in-use', ['count' => $count]);
    }
}
