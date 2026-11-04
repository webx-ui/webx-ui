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

    /** A hold ends within a day (§9.3): capture it or let it go. */
    public const string HOLD_EXPIRING = 'hold_expiring';

    /** The gateway refused a capture (§9.4): the attempt stays `authorized`. */
    public const string CAPTURE_FAILED = 'capture_failed';

    /** Problems of a hold: a capture or a void puts them right, not «resolved» (§9.4). */
    public const array HOLD_PROBLEMS = [self::HOLD_EXPIRING, self::CAPTURE_FAILED];

    public function __construct(
        public Outcome $outcome,
        public PaymentStatus $before,
        public PaymentStatus $after,
        public ?Reported $reported = null,
        public ?string $message = null,
        public ?string $problem = null,
        /** A dry run (§9.5): what would be, nothing written and the gateway not asked. */
        public bool $dryRun = false,
        /** A capture refused: the gateway's own words, without what was asked. */
        public ?string $refusal = null,
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
