<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Orders\Data\PublicStatusData;
use WebxUi\Orders\Data\StatusData;

/**
 * What `moveTo()` did or, on a dry run, would do (§5.3): the panel and the agent show it before
 * the move — whether the buyer sees a change, which milestone comes with it, who gets a message —
 * and `refusal` says why it cannot be done, as a code.
 */
final readonly class StatusMove
{
    /** The status is not offered any more: switched off. */
    public const string INACTIVE = 'inactive';

    /** The status is deleted: orders leave it, none come in (§5.6). */
    public const string DELETED = 'deleted';

    /** Its milestone is not allowed on this order — «paid» on a cancelled one (§5.2). */
    public const string CANCELLED = 'cancelled';

    /**
     * @param  bool  $moved  false — the order is in that status already, which is silence
     * @param  Milestone|null  $milestone  the one the move sets — none when it is there already
     * @param  list<int>  $notifications  the channels of the new public status, when it changes
     */
    public function __construct(
        public StatusData $from,
        public StatusData $to,
        public PublicStatusData $publicFrom,
        public PublicStatusData $publicTo,
        public bool $publicChanged,
        public bool $moved,
        public ?Milestone $milestone,
        public array $notifications,
        public ?string $refusal = null,
        public bool $dryRun = false,
    ) {}

    /** The same move without its milestone: a cancellation sets that one itself (decision 88). */
    public function without(): self
    {
        return new self($this->from, $this->to, $this->publicFrom, $this->publicTo, $this->publicChanged, $this->moved, null, $this->notifications, $this->refusal, $this->dryRun);
    }
}
