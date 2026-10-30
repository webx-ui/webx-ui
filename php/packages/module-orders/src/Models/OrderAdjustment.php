<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A step of the totals chain as it was at placing: below zero a discount, above it a charge —
 * delivery among them (spec §3). `item_id` — of a line; null — of the whole order. The label is a
 * snapshot in the order's language: the rule may be renamed tomorrow.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $item_id
 * @property string $source
 * @property string $label
 * @property int $amount
 * @property array<string, mixed>|null $meta
 */
class OrderAdjustment extends Model
{
    protected $table = 'order_adjustments';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'item_id' => 'integer',
            'amount' => 'integer',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'item_id');
    }
}
