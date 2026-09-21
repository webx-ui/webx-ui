<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Consent\ConsentScreen;
use WebxUi\Auth\Http\Middleware\SignInBeforeConsent;
use WebxUi\Mcp\Grants\Grant;

/**
 * The two things a person can do on the consent screen besides cancel.
 *
 * Allowing is Passport's own step with one addition: the fact of it is written down. There
 * is no "I understand" checkbox — it adds a click and proves nothing — so the record is the
 * consent: who, which client, where the answer went, whether they said "read only", and
 * which text they were shown. Passport keeps none of that, and a refreshed token would not
 * carry it if it did.
 */
final class ConsentController extends ApproveAuthorizationController
{
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        // Read before Passport pulls it out of the session, and written after Passport has
        // accepted it: a grant for an authorization that was refused would be a record of
        // nothing.
        $authRequest = $this->peek($request);

        $response = parent::approve($request, $psrResponse);

        if ($authRequest !== null) {
            $this->record($request, $authRequest);
        }

        return $response;
    }

    /**
     * "Sign in as somebody else": end this session and come back here through the panel's
     * own sign-in, which is the one thing a page outside the panel cannot draw itself.
     */
    public function switch(Request $request, AuthFactory $auth): RedirectResponse
    {
        $guard = $auth->guard((string) config('webx-auth.guard'));

        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $next = $request->input('next');

        return new RedirectResponse(SignInBeforeConsent::signInUrl(is_string($next) ? $next : null));
    }

    /**
     * The authorization request as Passport put it in the session, if the form's token is
     * the one it issued. A mismatch is Passport's to refuse, in the line after this one.
     */
    private function peek(Request $request): ?AuthorizationRequestInterface
    {
        if ($request->isNotFilled('auth_token') || $request->session()->get('authToken') !== $request->input('auth_token')) {
            return null;
        }

        $serialized = $request->session()->get('authRequest');

        if (! is_string($serialized)) {
            return null;
        }

        $authRequest = unserialize($serialized, ['allowed_classes' => [
            AuthorizationRequest::class, Client::class, Scope::class, User::class,
        ]]);

        return $authRequest instanceof AuthorizationRequestInterface ? $authRequest : null;
    }

    private function record(Request $request, AuthorizationRequestInterface $authRequest): void
    {
        $user = $request->user();

        if ($user === null) {
            return;
        }

        $client = $authRequest->getClient();
        $redirect = $authRequest->getRedirectUri() ?? $client->getRedirectUri();

        Grant::query()->updateOrCreate(
            [
                'cms_user_id' => $user->getAuthIdentifier(),
                'oauth_client_id' => $client->getIdentifier(),
            ],
            [
                'client_name' => $client->getName(),
                'redirect_host' => ConsentScreen::host(is_array($redirect) ? (string) ($redirect[0] ?? '') : $redirect),
                'read_only' => $request->boolean('read_only'),
                'consent_version' => ConsentScreen::VERSION,
                'created_at' => Carbon::now(),
                'last_used_at' => null,
                'revoked_at' => null,
            ],
        );
    }
}
