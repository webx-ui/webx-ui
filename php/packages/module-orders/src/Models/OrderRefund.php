<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money given back (spec §3, §9): the amount in money, what each tender got back and which units
 * were returned. In v1 always `manual` — the money went back outside the site, and this is the
 * record of it (decision 27).
 *
 * @property int $id
 * @property int $order_id
 * @property int $amount
 * @property list<array{tender_id: int, amount: int}> $tenders
 * @property list<array{item_id: int, qty: int}> $items
 * @property string $method
 * @property string|null $reference
 * @property string|null $reason
 * @property string $source
 * @property int|null $admin_id
 * @property Carbon|null $created_at
 */
class OrderRefund extends Model
{
    protected $table = 'order_refunds';

    /** The money went back outside the site: a bank, a till, the ledger (§9.4). */
    public const string MANUAL = 'manual';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'integer',
            'tenders' => 'array',
            'items' => 'array',
            'admin_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
