<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A privilege the order came with — «the shop pays for delivery» (spec §3): a mark for the
 * manager and the waybill, with no amount of its own.
 *
 * @property int $id
 * @property int $order_id
 * @property string $key
 * @property string $label
 * @property string $source
 */
class OrderBenefit extends Model
{
    protected $table = 'order_benefits';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
