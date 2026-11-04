<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** What the gateway says of a payment (spec §6.3), in the base's terms. */
final readonly class GatewayStatus
{
    /** @param  array<string, scalar|null>  $meta  non-secret: the masked card as sent '4444****1111', the kind of payment, a fee */
    public function __construct(
        public Reported $status,
        public ?string $gatewayRef = null,
        /** What the gateway took (Paid) or holds (Authorized), minor units. */
        public ?int $amount = null,
        public ?string $currency = null,
        public ?CarbonImmutable $paidAt = null,
        /** The gateway's words for a failure; no card data. */
        public ?string $reason = null,
        public ?int $refundedAmount = null,
        public ?CarbonImmutable $holdExpiresAt = null,
        public array $meta = [],
        /** The gateway knows no such payment because the buyer never began it (§8.4): Pending only. */
        public bool $notStarted = false,
        /**
         * The bank turned the card down, yet the gateway keeps the payment open for another try on
         * its page (§8.4): Pending only, `$reason` says why. The buyer is told at once.
         */
        public bool $declined = false,
    ) {
        if ($notStarted && $status !== Reported::Pending) {
            throw new InvalidArgumentException('A payment that is not started can only be Pending, not '.$status->value.'.');
        }

        if ($declined && ($status !== Reported::Pending || $notStarted)) {
            throw new InvalidArgumentException('A declined payment is Pending and was started, not '.$status->value.'.');
        }
    }
}
