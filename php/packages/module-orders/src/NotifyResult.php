<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What sending a message by hand did, or would do (decision 99): the event, the rows of the log it
 * wrote — each channel, recipient and status — and, for a dry run of «send the buyer a letter»,
 * the message each channel would send. `resentFrom` is the row repeated, when it repeats one.
 */
final readonly class NotifyResult
{
    /**
     * @param  list<array{id: int|null, channel: string, provider: string, recipient: string, status: string}>  $deliveries
     * @param  list<array{channel: string, provider: string, recipient: string, subject: string|null, body: string, plain: string, html: bool}>  $previews
     */
    public function __construct(
        public string $event,
        public bool $dryRun,
        public array $deliveries = [],
        public array $previews = [],
        public ?int $resentFrom = null,
    ) {}

    /** @return list<string> the addresses it went, or would go, to */
    public function recipients(): array
    {
        return array_values(array_unique(array_map(static fn (array $row): string => $row['recipient'], $this->deliveries)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'dry_run' => $this->dryRun,
            'resent_from' => $this->resentFrom,
            'deliveries' => $this->deliveries,
            'previews' => $this->previews,
        ];
    }
}
