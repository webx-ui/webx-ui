<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The error pages of the chain (spec §4, `views/errors/`): a 404 in the site's header and footer
 * rather than Laravel's stock page.
 *
 * Laravel does not find them by itself. It looks for `errors::404`, and rebuilds that namespace
 * from `config('view.paths')` on every error it renders — the theme layers, added to the finder
 * as locations, are never part of it. So the handler is asked first: a page that wants HTML and
 * has `errors.<status>` somewhere in the lookup — the site's `resources/views/errors`, then each
 * layer — gets that view; everything else goes on to Laravel exactly as before.
 *
 * The exception is rendered inside the route's middleware, so the page is in the language of
 * the request's prefix and the header sees the request as it was.
 */
final readonly class ErrorPages
{
    public function __construct(private Factory $views) {}

    public function render(HttpExceptionInterface $e, Request $request): ?Response
    {
        $view = 'errors.'.$e->getStatusCode();

        if ($request->expectsJson() || ! $this->views->exists($view)) {
            return null;
        }

        // Rendered here rather than handed over as a view: a page whose header needs the
        // database it cannot reach must fall back to Laravel's page, not turn a 503 into a 500.
        try {
            $html = $this->views->make($view, ['exception' => $e])->render();
        } catch (Throwable $failure) {
            report($failure);

            return null;
        }

        return new Response($html, $e->getStatusCode(), $e->getHeaders());
    }
}
