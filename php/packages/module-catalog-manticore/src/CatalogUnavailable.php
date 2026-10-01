<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A catalogue too large for the database to stand in, with Manticore down (decision 12 of the
 * Manticore spec): 503 with `Retry-After`, which a search engine waits out — not a 500, and not an
 * empty category with 200, which it would remember.
 *
 * The page is the site's, header, menu and footer in place ({@see render()}): a reader who came
 * for a category is told to come back in a minute, not shown a bare error.
 */
final class CatalogUnavailable extends HttpException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct(503, 'The catalogue is temporarily unavailable.', null, ['Retry-After' => (string) $retryAfter]);
    }

    /** What Laravel's handler sends instead of its own error page. */
    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $this->getMessage()], 503, $this->getHeaders());
        }

        return response()->view('webx-catalog-manticore::unavailable', [], 503, $this->getHeaders());
    }
}
