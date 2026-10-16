<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Sending;

/**
 * Somebody a channel writes to, as of now: a chat id or an address, with the words to show for
 * it. With a `skip` reason it is somebody the channel names but cannot reach — an administrator
 * switched off, an addressee the event brought no address for.
 */
final readonly class Recipient
{
    public function __construct(
        public string $key,
        public ?string $label = null,
        public ?string $skip = null,
    ) {}

    public static function skipped(string $key, string $reason, ?string $label = null): self
    {
        return new self($key, $label, $reason);
    }
}
