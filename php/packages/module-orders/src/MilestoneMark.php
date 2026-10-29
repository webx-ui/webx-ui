<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Carbon\CarbonImmutable;
use WebxUi\Orders\Data\TenderData;

/**
 * What `mark()` answers (§5.1). A milestone is set once: a second call is not an error but
 * silence, and the answer is the first mark — its time and source — so a tracking that comes
 * after the manager's own mark knows it changed nothing.
 *
 * `cancel()` adds what the cancellation does (§9.1): whether the order was paid and how much
 * money is left to give back — a cancellation does not give it — and the tenders it gives back.
 */
final readonly class MilestoneMark
{
    /** Set by this call; its event goes after the commit. */
    public const string MARKED = 'marked';

    /** It was there already: the first mark is returned, nothing is written or dispatched. */
    public const string ALREADY_MARKED = 'already_marked';

    /** `cancelled` of an order given back whole: nothing is left to cancel, so nothing is done. */
    public const string REFUNDED = 'refunded';

    public function __construct(
        public Milestone $milestone,
        public string $outcome,
        public CarbonImmutable $at,
        public string $source,
        public ?int $adminId = null,
        public ?string $reference = null,
        public ?string $note = null,
        public bool $wasPaid = false,
        public int $moneyToRefund = 0,
        /** @var list<TenderData> `amount` — what goes back to the wallet now */
        public array $tendersReturned = [],
        public bool $dryRun = false,
    ) {}

    /**
     * The same mark with what a cancellation does.
     *
     * @param  list<TenderData>  $tendersReturned
     */
    public function cancelling(bool $wasPaid, int $moneyToRefund, array $tendersReturned, bool $dryRun): self
    {
        return new self(
            $this->milestone, $this->outcome, $this->at, $this->source, $this->adminId, $this->reference, $this->note,
            $wasPaid, $moneyToRefund, $tendersReturned, $dryRun,
        );
    }

    public function marked(): bool
    {
        return $this->outcome === self::MARKED;
    }

    public function alreadyMarked(): bool
    {
        return $this->outcome === self::ALREADY_MARKED;
    }
}
