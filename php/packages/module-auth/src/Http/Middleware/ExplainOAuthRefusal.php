<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Middleware;

use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Consent\ConsentScreen;

/**
 * A refusal of the consent screen, said to the person in front of it rather than in JSON.
 *
 * Passport answers a request it will not show the screen for — an unknown application, an
 * address it was not registered with — with the OAuth error body, which is right for a program
 * and is what a browser printed as `{"error":"invalid_client",…}` on the page a person had been
 * sent to. A browser gets the panel's page with the same status and the code beside it; anything
 * that does not ask for HTML still gets the body as it was. A refusal that goes back to the
 * application is a redirect and passes untouched: the application tells it.
 */
final class ExplainOAuthRefusal
{
    public function __construct(
        private readonly ResponseFactory $responses,
        private readonly ConsentScreen $screen,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() < 400 || ! $request->acceptsHtml() || $request->expectsJson()) {
            return $response;
        }

        $body = json_decode((string) $response->getContent(), true);

        if (! is_array($body) || ! is_string($body['error'] ?? null)) {
            return $response;
        }

        return $this->responses->view('webx-auth::refusal', [
            'brand' => $this->screen->brand(),
            'code' => $body['error'],
        ], $response->getStatusCode());
    }
}
