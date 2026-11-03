<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Delivery\Shipping\ShipmentStatus;

/**
 * A parcel of an order (§5, §11): the waybill at the carrier and where the parcel is. `events`
 * keeps the carrier's last statuses with their dates, newest first, at most fifty.
 *
 * @property int $id
 * @property int $order_id
 * @property string $carrier
 * @property string $type
 * @property string $method_key
 * @property string|null $number
 * @property string $payer
 * @property int|null $cost
 * @property ShipmentStatus $status
 * @property Carbon|null $status_at
 * @property bool $final
 * @property Carbon|null $next_check_at
 * @property list<array{status: string, at: string, text: string|null}> $events
 * @property array<string, mixed> $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DeliveryShipment extends Model
{
    /** How many of the carrier's statuses a parcel keeps. */
    public const int EVENTS = 50;

    protected $table = 'delivery_shipments';

    protected $fillable = [
        'order_id', 'carrier', 'type', 'method_key', 'number', 'payer', 'cost', 'status', 'status_at',
        'final', 'next_check_at', 'events', 'meta',
    ];

    protected $attributes = [
        'final' => false,
        'events' => '[]',
        'meta' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'cost' => 'integer',
            'status' => ShipmentStatus::class,
            'status_at' => 'datetime',
            'final' => 'boolean',
            'next_check_at' => 'datetime',
            'events' => 'array',
            'meta' => 'array',
        ];
    }
}
