<?php

declare(strict_types=1);

namespace WebxUi\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\PaymentStatus;

/**
 * One attempt to pay an order (spec §5): a gateway's payment, or a manager's mark. Never deleted —
 * it is the trace of money.
 *
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string $method_key
 * @property string $type
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $problem
 * @property string|null $gateway_ref
 * @property int|null $paid_amount
 * @property Carbon|null $paid_at
 * @property string|null $failure_reason
 * @property Carbon|null $expires_at
 * @property bool $hold
 * @property int|null $authorized_amount
 * @property Carbon|null $authorized_at
 * @property Carbon|null $hold_expires_at
 * @property int|null $capture_amount
 * @property int|null $refunded_amount
 * @property string $source
 * @property int|null $admin_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Payment extends Model
{
    /** The key of `meta`: the gateway's last word was «the buyer never began this payment» (§8.4). */
    public const string NOT_STARTED = 'not_started';

    /** The key of `meta`: the gateway's last word was «the bank declined, try again» (§8.4); the reason or `true`. */
    public const string DECLINED = 'declined';

    protected $table = 'payments';

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'created',
        'hold' => false,
    ];

    protected static function booted(): void
    {
        self::creating(static function (Payment $payment): void {
            if (($payment->attributes['uuid'] ?? '') === '') {
                $payment->uuid = (string) Str::ulid();
            }
        });
    }

    public function notStarted(): bool
    {
        return $this->status === PaymentStatus::Pending && isset(($this->meta ?? [])[self::NOT_STARTED]);
    }

    public function declined(): bool
    {
        return $this->status === PaymentStatus::Pending && isset(($this->meta ?? [])[self::DECLINED]);
    }

    /** What the bank said of the card, as the gateway's words have it; null — it said nothing. */
    public function declineReason(): ?string
    {
        $reason = $this->declined() ? ($this->meta ?? [])[self::DECLINED] : null;

        return is_string($reason) ? $reason : null;
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'paid_amount' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'hold' => 'boolean',
            'authorized_amount' => 'integer',
            'authorized_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'capture_amount' => 'integer',
            'refunded_amount' => 'integer',
            'admin_id' => 'integer',
            'meta' => 'array',
        ];
    }
}
