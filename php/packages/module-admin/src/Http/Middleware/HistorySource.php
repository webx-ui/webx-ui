<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\History\HistoryContext;

/**
 * `webx.history` — says for the journal which door a request came through and who is behind it.
 *
 * The panel's API group carries it (appended by the frame, after whatever authenticates), so
 * every save made from the panel is `panel` with the signed-in administrator. A site's own API
 * that edits the same records puts `webx.history:api` on its routes.
 */
final class HistorySource
{
    public function __construct(private readonly HistoryContext $context) {}

    public function handle(Request $request, Closure $next, string $source = HistoryContext::PANEL): Response
    {
        $this->context->set($source, $request->user());

        return $next($request);
    }
}
