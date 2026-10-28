<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Visitors\AnswersUnverified;
use WebxUi\Admin\Visitors\Visitors;

/**
 * `webx.visitor` — the route is for a signed-in visitor of the site; `webx.visitor:verified` —
 * for one whose e-mail is confirmed.
 *
 * A guest is sent to sign in and brought back (401 `visitor.guest` for JSON). A site with no
 * visitors module has nowhere to send them, and the answer is 404: the feature is not on this
 * site. An unconfirmed visitor gets the visitors module's «Confirm your e-mail» page (403
 * `visitor.unverified` for JSON).
 */
final class RequireVisitor
{
    public const VERIFIED = 'verified';

    public function __construct(private readonly Visitors $visitors) {}

    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        if ($requirement !== null && $requirement !== self::VERIFIED) {
            throw new InvalidArgumentException("webx.visitor takes no requirement [{$requirement}]; the only one is [verified].");
        }

        $visitor = $this->visitors->current();

        if ($visitor === null) {
            $signIn = $this->visitors->signInUrl($request->fullUrl());

            if ($signIn === null) {
                abort(404);
            }

            return $request->expectsJson()
                ? new JsonResponse(['code' => 'visitor.guest'], 401)
                : redirect()->to($signIn);
        }

        if ($requirement === self::VERIFIED && ! $visitor->verified) {
            if ($request->expectsJson()) {
                return new JsonResponse(['code' => 'visitor.unverified'], 403);
            }

            if ($this->visitors instanceof AnswersUnverified) {
                return $this->visitors->unverifiedResponse($request);
            }

            abort(403);
        }

        return $next($request);
    }
}
