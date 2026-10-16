<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Sending;

use RuntimeException;

/**
 * A message the other side did not take (§4.1). The words are the provider's, without secrets.
 *
 * `permanent` is a failure another attempt will not change — a token refused, a chat the bot is
 * not in — and `retryAfter` is how long the other side asked to wait (Telegram's 429).
 */
final class DeliveryFailed extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $permanent = false,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function permanent(string $message): self
    {
        return new self($message, true);
    }
}
