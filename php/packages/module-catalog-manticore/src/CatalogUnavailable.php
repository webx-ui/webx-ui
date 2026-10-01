<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A catalogue too large for the database to stand in, with Manticore down (decision 12 of the
 * Manticore spec): 503 with `Retry-After`, which a search engine waits out — not a 500, and not an
 * empty category with 200, which it would remember.
 */
final class CatalogUnavailable extends HttpException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct(503, 'The catalogue is temporarily unavailable.', null, ['Retry-After' => (string) $retryAfter]);
    }
}
