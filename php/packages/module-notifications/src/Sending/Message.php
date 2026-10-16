<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Sending;

/**
 * A message rendered for one channel: the subject if the provider has one, the body in the
 * provider's markup, and the same text without markup.
 */
final readonly class Message
{
    public function __construct(
        public ?string $subject,
        public string $body,
        public string $plain,
        public bool $truncated = false,
        public ?string $replyTo = null,
    ) {}
}
