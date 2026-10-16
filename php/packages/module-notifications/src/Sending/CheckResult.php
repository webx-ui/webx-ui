<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Sending;

/** What checking a channel's settings found: whether it works, in words, and what it learned. */
final readonly class CheckResult
{
    /**
     * @param  array<string, string>  $details  e.g. `bot` — the bot's `@username`
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public array $details = [],
    ) {}

    /**
     * @return array{ok: bool, message: string, details: array<string, string>}
     */
    public function toArray(): array
    {
        return ['ok' => $this->ok, 'message' => $this->message, 'details' => $this->details];
    }
}
