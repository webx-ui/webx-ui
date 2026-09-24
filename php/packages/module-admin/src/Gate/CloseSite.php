<?php

declare(strict_types=1);

namespace WebxUi\Admin\Gate;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A password over the whole site while it is being tested, and over nothing the panel needs.
 *
 * Global middleware rather than a member of `web`, for the same reason the SEO redirects are: a
 * request for an address with no route never reaches a group — the router throws first — and a
 * gate that lets every 404 through tells a stranger which addresses exist. Registered on
 * `booted`, so that the HTTP kernel copying its groups over the router's cannot undo it.
 *
 * Off unless `WEBX_SITE_GATE` says otherwise, and closed to everyone when it is on and no pair
 * is given: a gate that is switched on is never quietly open.
 *
 * What the web server answers by itself — `/storage`, `/build`, anything else in `public` —
 * never reaches PHP, so a direct link to an uploaded file still works on a closed site. Closing
 * that too is a rule on the proxy, which would then have to repeat these openings for itself.
 */
final class CloseSite
{
    public function __construct(
        private readonly Credentials $credentials,
        private readonly Openings $openings,
        private readonly Config $config,
        private readonly ViewFactory $views,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->credentials->enabled() || $this->openings->open($request)) {
            return $next($request);
        }

        if (! $this->credentials->accepts($request->getUser(), $request->getPassword())) {
            return $this->refuse();
        }

        $response = $next($request);

        // The page behind the password is not the page anyone else gets: a shared cache that
        // ignored the header would hand it to the next visitor without asking.
        $response->headers->set('Vary', 'Authorization', false);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function refuse(): Response
    {
        $realm = str_replace(['"', '\\'], '', (string) ($this->config->get('app.name') ?: 'Site'));

        // The body is what shows when the dialog is cancelled; the dialog itself is the browser's.
        return new IlluminateResponse($this->views->make('webx-admin::gate')->render(), 401, [
            'WWW-Authenticate' => 'Basic realm="'.$realm.'", charset="UTF-8"',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-store',
            'Vary' => 'Authorization',
        ]);
    }
}
