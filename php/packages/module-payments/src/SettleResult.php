<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use WebxUi\Payments\Exchange\Outcome;

/**
 * What `Payments::settle()` made of what the gateway said (spec §9.1): the outcome for the
 * journal, the attempt's status before and after, the words why, and a problem when the money
 * came where it should not have (§9.4).
 */
final readonly class SettleResult
{
    public const string DUPLICATE = 'duplicate';

    public const string AMOUNT_MISMATCH = 'amount_mismatch';

    public const string ORDER_CANCELLED = 'order_cancelled';

    public function __construct(
        public Outcome $outcome,
        public PaymentStatus $before,
        public PaymentStatus $after,
        public ?Reported $reported = null,
        public ?string $message = null,
        public ?string $problem = null,
    ) {}

    /** Nothing moved: the attempt is where it was, and nobody asked the gateway. */
    public static function untouched(PaymentStatus $status, ?string $message = null): self
    {
        return new self(Outcome::Same, $status, $status, null, $message);
    }

    public function changed(): bool
    {
        return $this->outcome === Outcome::Applied;
    }
}
