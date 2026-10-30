<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An edit of the order's lines (spec §38.1): what was added, changed and taken out, «before —
 * after», with names in the order's language as a snapshot. An edit is a document like the order:
 * never changed or deleted — a wrong one is put right by the next.
 *
 * @property int $id
 * @property int $order_id
 * @property int $number
 * @property list<array{op: string, item_id: int, name: string, options_label: string|null, price: int, qty_from: int, qty_to: int, total_from: int, total_to: int}> $changes
 * @property list<array{adjustment_id: int, label: string, amount_from: int, amount_to: int}> $adjustments
 * @property array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int} $before
 * @property array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int} $after
 * @property string|null $reason
 * @property string|null $message
 * @property string $source
 * @property int|null $admin_id
 * @property Carbon|null $created_at
 */
class OrderRevision extends Model
{
    protected $table = 'order_revisions';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'number' => 'integer',
            'changes' => 'array',
            'adjustments' => 'array',
            'before' => 'array',
            'after' => 'array',
            'admin_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
