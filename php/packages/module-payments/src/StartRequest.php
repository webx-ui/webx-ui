<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Carbon\CarbonImmutable;

/**
 * What the base asks a gateway to open (spec §6.3): the attempt and the order as far as the
 * gateway needs them. The buyer's data and the lines are there only when the type's `needs()`
 * asked for them (decisions 42, 43).
 */
final readonly class StartRequest
{
    /** @param  list<PaymentLine>  $lines  empty unless needs()->lines; never empty when asked */
    public function __construct(
        /** Our attempt's id: the gateway's order id / merchant reference. */
        public string $uuid,
        /** '1024' — what a manager searches by in the gateway's cabinet. */
        public string $orderNumber,
        /** Minor units; the satellite converts without a float (`GatewayAmount`). */
        public int $amount,
        public string $currency,
        /** "Order 1024" in the order's language. */
        public string $description,
        /** /pay/return/{uuid} — absolute. */
        public string $returnUrl,
        /** /payments/notify/{method} — absolute. */
        public string $notifyUrl,
        /** The gateway page's language, if it can. */
        public string $locale,
        /** Open with a hold (§9.5); true only for CapturesPayments. */
        public bool $hold = false,
        /** The method's own term of a payment; null — the gateway's default. */
        public ?CarbonImmutable $expiresAt = null,
        public ?string $customerEmail = null,
        public ?string $customerPhone = null,
        public ?string $customerName = null,
        public array $lines = [],
    ) {}
}
