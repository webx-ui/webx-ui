<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Orders\Milestone;

/**
 * A milestone of an order — once each, never taken back (spec §3, decision 24): when it happened,
 * who or what set it, and the reference to check it against (a payment id, a waybill number).
 *
 * @property int $id
 * @property int $order_id
 * @property Milestone $milestone
 * @property Carbon $at
 * @property string $source
 * @property int|null $admin_id
 * @property string|null $reference
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class OrderMilestone extends Model
{
    protected $table = 'order_milestones';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'milestone' => Milestone::class,
            'at' => 'datetime',
            'admin_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
