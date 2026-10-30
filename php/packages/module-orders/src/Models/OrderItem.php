<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A line of the order, frozen at placing: the name in the languages of the site, the price and
 * the discount of that moment (spec §3). Unlike the cart, a child's `qty` is absolute — already
 * multiplied by its parent: nothing recalculates an order, and a waybill counts units.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $parent_id
 * @property int $position
 * @property string $type
 * @property int $ref_id
 * @property array<string, mixed>|null $options
 * @property string|null $sku
 * @property array<string, string> $name
 * @property string|null $options_label
 * @property string|null $image
 * @property string|null $source
 * @property string|null $label
 * @property int $price
 * @property int $qty
 * @property int $adjustments_total
 * @property int $total
 * @property bool $shippable
 * @property int $refunded_qty
 * @property int $revision the edit the line came with (§37.4); 0 — placed with the order
 * @property Carbon|null $removed_at taken out by an edit: `qty` is what was refunded of it, often 0
 * @property-read Order $order
 * @property-read OrderItem|null $parent
 * @property-read Collection<int, OrderItem> $children
 */
class OrderItem extends Model
{
    protected $table = 'order_items';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'parent_id' => 'integer',
            'position' => 'integer',
            'ref_id' => 'integer',
            'options' => 'array',
            'name' => 'array',
            'price' => 'integer',
            'qty' => 'integer',
            'adjustments_total' => 'integer',
            'total' => 'integer',
            'shippable' => 'boolean',
            'refunded_qty' => 'integer',
            'revision' => 'integer',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * A line the order still shows (§37.4): one an edit took out whole is history only — the
     * edit's row and its line in the timeline name it. Taken out with units refunded, it stays:
     * the refunds point at them.
     */
    public function shown(): bool
    {
        return $this->removed_at === null || $this->qty > 0;
    }

    /**
     * The name in the language asked for, else the order's, else whichever there is: a product
     * renamed or deleted since does not change what was bought.
     */
    public function nameIn(?string $locale = null): string
    {
        $names = $this->name;

        return (string) ($names[$locale ?? ''] ?? $names[$this->order->locale] ?? reset($names) ?: '');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }
}
