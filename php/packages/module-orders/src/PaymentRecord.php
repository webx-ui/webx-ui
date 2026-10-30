<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Carbon\CarbonImmutable;

/**
 * What `recordPayment()` answers (§36.3): the money that came in, the balance after it, and on a
 * dry run a refusal in the answer instead of an exception — the panel's dialog and
 * `orders_payment_record` show the same thing from it.
 */
final readonly class PaymentRecord
{
    /** The first payment of an order is `mark(paid)` — its milestone and its event. */
    public const string NOT_PAID = 'not_paid';

    /** Money that came to a cancelled order is a refund to make, not a payment. */
    public const string CANCELLED = 'cancelled';

    public function __construct(
        public int $amount,
        public ?string $reference,
        public ?string $note,
        public CarbonImmutable $at,
        public Balance $before,
        public Balance $after,
        public ?string $refusal,
        public bool $dryRun,
    ) {}
}
