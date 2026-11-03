<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use RuntimeException;
use Throwable;

/**
 * The carrier's API failed (§7.6). The message is for the log; the panel and MCP say the
 * failure's words. The page is tried again from the same cursor; nothing is removed.
 */
final class CarrierUnavailable extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly SyncFailure $failure = SyncFailure::NoAnswer,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function keyRejected(string $message): self
    {
        return new self($message, SyncFailure::KeyRejected);
    }

    public static function badResponse(string $message): self
    {
        return new self($message, SyncFailure::BadResponse);
    }
}
