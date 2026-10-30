<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An order: the cart as it was at placing — lines, money, contacts, the blocks of delivery and
 * payment — and what happened to it since (spec §3).
 *
 * Read it freely; write it only through `Orders` (decision 18). The copies of the milestones on
 * this row exist for the filters of the list, and `order_milestones` is where they are true.
 *
 * @property int $id
 * @property string|null $number
 * @property string|null $idempotency_key
 * @property int|null $user_id
 * @property int|null $cart_id
 * @property string $locale
 * @property string $currency
 * @property int $status_id
 * @property int $subtotal
 * @property int $adjustments_total
 * @property int $total
 * @property int $tenders_total
 * @property int $payable
 * @property int $refunded_total
 * @property int $paid_total
 * @property int $balance
 * @property string $customer_first_name
 * @property string|null $customer_last_name
 * @property string|null $customer_phone
 * @property string|null $customer_phone_key
 * @property string|null $customer_email
 * @property array{first_name: string, last_name: string|null, phone: string|null}|null $recipient
 * @property string|null $comment
 * @property string|null $manager_note
 * @property array<string, mixed>|null $delivery
 * @property array<string, mixed>|null $payment
 * @property Carbon $placed_at
 * @property bool $awaiting_payment
 * @property Carbon|null $payment_failed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $received_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OrderStatus $status
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderAdjustment> $adjustments
 * @property-read Collection<int, OrderTender> $tenders
 * @property-read Collection<int, OrderBenefit> $benefits
 * @property-read Collection<int, OrderMilestone> $milestones
 * @property-read Collection<int, OrderRefund> $refunds
 * @property-read Collection<int, StatusChange> $history
 */
class Order extends Model
{
    protected $table = 'orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cart_id' => 'integer',
            'status_id' => 'integer',
            'subtotal' => 'integer',
            'adjustments_total' => 'integer',
            'total' => 'integer',
            'tenders_total' => 'integer',
            'payable' => 'integer',
            'refunded_total' => 'integer',
            'paid_total' => 'integer',
            'balance' => 'integer',
            'recipient' => 'array',
            'delivery' => 'array',
            'payment' => 'array',
            'placed_at' => 'datetime',
            'awaiting_payment' => 'boolean',
            'payment_failed_at' => 'datetime',
            'paid_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * «First Last» — for letters, the list and search; the snapshot keeps them apart
     * (decision 40).
     */
    public function customerName(): string
    {
        return trim($this->customer_first_name.' '.($this->customer_last_name ?? ''));
    }

    /**
     * The internal status, deleted ones too: an order stays in a status that was deleted after it
     * came there (§5.6).
     *
     * @return BelongsTo<OrderStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id')->withTrashed();
    }

    /**
     * Every line, children too, in the cart's order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<OrderAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class)->orderBy('id');
    }

    /** @return HasMany<OrderTender, $this> */
    public function tenders(): HasMany
    {
        return $this->hasMany(OrderTender::class)->orderBy('id');
    }

    /** @return HasMany<OrderBenefit, $this> */
    public function benefits(): HasMany
    {
        return $this->hasMany(OrderBenefit::class)->orderBy('id');
    }

    /** @return HasMany<OrderMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(OrderMilestone::class)->orderBy('at')->orderBy('id');
    }

    /** @return HasMany<OrderRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class)->orderBy('id');
    }

    /** @return HasMany<StatusChange, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(StatusChange::class)->orderBy('id');
    }
}
