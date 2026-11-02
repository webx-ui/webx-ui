<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

use WebxUi\Delivery\Contracts\DescribesWaybill;

/**
 * What a waybill is made of (§11.2): the order's delivery block as it was placed, who gets the
 * parcel, who sends it, what is in it, what it is worth and who pays the carrier. The manager
 * may change the recipient, the declared value, the cash on delivery and the carrier's own fields
 * in the dialog; the rest is the order's.
 */
final readonly class WaybillRequest
{
    public const string SENDER = 'sender';

    public const string RECIPIENT = 'recipient';

    /**
     * @param  array<string, mixed>  $delivery  the order's block `delivery` (§11.1)
     * @param  array<string, mixed>  $sender  the carrier's settings of the sender (§13.2)
     * @param  list<array{name: string, sku: string|null, qty: int, price: int}>  $lines  what travels, for the inventory
     * @param  array<string, mixed>  $extra  the carrier's own fields of the waybill, by the names its type declares ({@see DescribesWaybill})
     */
    public function __construct(
        public int $orderId,
        public ?string $orderNumber,
        public array $delivery,
        /** The order keeps the names apart (decision 40 of the orders' spec): no splitting by spaces. */
        public string $recipientFirstName,
        public string $recipientLastName,
        public ?string $recipientPhone,
        public ?string $recipientEmail,
        public array $sender,
        public array $lines,
        /** Minor units of the order's currency: the order's total unless the manager says otherwise. */
        public int $declaredValue,
        public string $currency,
        /** self::SENDER | self::RECIPIENT — §9.3. */
        public string $payer,
        /** Cash on delivery to collect, or null — none. */
        public ?int $cod = null,
        /** The carrier's own id of the locality of the snapshot (`delivery_carrier_localities.external_id`), or null — not bound. */
        public ?string $carrierLocality = null,
        public array $extra = [],
    ) {}

    public function carrier(): string
    {
        return (string) ($this->delivery['carrier'] ?? '');
    }

    public function type(): string
    {
        return (string) ($this->delivery['type'] ?? '');
    }

    /** The name in one line, for a carrier that takes no first and last name apart. */
    public function recipientName(): string
    {
        return trim($this->recipientFirstName.' '.$this->recipientLastName);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'carrier' => $this->carrier(),
            'type' => $this->type(),
            'method' => $this->delivery['method'] ?? null,
            'recipient' => [
                'first_name' => $this->recipientFirstName,
                'last_name' => $this->recipientLastName,
                'name' => $this->recipientName(),
                'phone' => $this->recipientPhone,
                'email' => $this->recipientEmail,
            ],
            'sender' => $this->sender,
            'lines' => $this->lines,
            'declared_value' => $this->declaredValue,
            'currency' => $this->currency,
            'payer' => $this->payer,
            'cod' => $this->cod,
            'point' => $this->delivery['point'] ?? null,
            'address' => $this->delivery['address'] ?? null,
            'locality' => $this->delivery['locality'] ?? null,
            'carrier_locality' => $this->carrierLocality,
            'extra' => (object) $this->extra,
        ];
    }
}
