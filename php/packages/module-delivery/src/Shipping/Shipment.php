<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

use WebxUi\Delivery\Models\DeliveryShipment;

/** A shipment as a method type is given it: a copy of the row, never the model (§6.1). */
final readonly class Shipment
{
    /** @param  array<string, mixed>  $meta  what the satellite kept with the waybill */
    public function __construct(
        public int $id,
        public int $orderId,
        public string $carrier,
        public string $type,
        public string $methodKey,
        public ?string $number,
        public string $payer,
        public ShipmentStatus $status,
        public array $meta = [],
    ) {}

    public static function of(DeliveryShipment $shipment): self
    {
        return new self(
            id: $shipment->id,
            orderId: $shipment->order_id,
            carrier: $shipment->carrier,
            type: $shipment->type,
            methodKey: $shipment->method_key,
            number: $shipment->number,
            payer: $shipment->payer,
            status: $shipment->status,
            meta: $shipment->meta ?? [],
        );
    }
}
