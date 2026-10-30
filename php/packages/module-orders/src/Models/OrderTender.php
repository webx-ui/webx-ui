<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment counted against the order — a gift card, cashback (spec §3): money already the
 * buyer's, not a discount. `refunded_amount` is how much of it went back, by a refund or a
 * cancellation; `meta` is what its owner needs to give it back.
 *
 * @property int $id
 * @property int $order_id
 * @property string $source
 * @property string $label
 * @property int $amount
 * @property int $refunded_amount
 * @property array<string, mixed>|null $meta
 */
class OrderTender extends Model
{
    protected $table = 'order_tenders';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
