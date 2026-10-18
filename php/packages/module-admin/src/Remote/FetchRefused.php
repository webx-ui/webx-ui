<?php

declare(strict_types=1);

namespace WebxUi\Admin\Remote;

use RuntimeException;

/**
 * Why a remote address was not fetched, in words meant for whoever asked — never the transport's
 * own error, which names internal hosts and ports. `reason` is one of the constants, for a caller
 * that says it in its own words; `status` is what the address answered, when it answered.
 */
final class FetchRefused extends RuntimeException
{
    public const SCHEME = 'scheme';

    public const HOST = 'host';

    public const PRIVATE = 'private';

    public const UNRESOLVED = 'unresolved';

    public const TOO_LARGE = 'too-large';

    public const TIMEOUT = 'timeout';

    public const UNREACHABLE = 'unreachable';

    public const STATUS = 'status';

    public const REDIRECTS = 'redirects';

    public function __construct(
        string $message,
        public readonly string $reason = self::UNREACHABLE,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }
}
