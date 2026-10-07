<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Antispam;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use WebxUi\Inbox\Models\Form;

/**
 * How often one address may write to one form (§7) — counted twice, and differently.
 *
 * `throttle` is how many submissions a minute one address may leave, and only a submission
 * that got through counts towards it. Counting every request was the first version: a visitor
 * who mistyped an address five times in a minute was then refused for the sixth, correct one,
 * and the form answered every attempt after that with a 429 the page has no words for.
 *
 * `attempts` is the other number, for everything that knocks, refused or not: high enough that
 * a person correcting their mistakes never meets it, low enough that a script trying the door
 * over and over is stopped before a controller, a model or the validator runs. It lives on the
 * route as the named limiter; the first one is checked by the controller, which is the only
 * place that knows whether a submission was accepted.
 */
final class Throttle
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly Config $config,
    ) {}

    /** The route's limiter: every request to the form, whatever became of it. */
    public function attempts(?Form $form, Request $request, string $slug): Limit
    {
        $accepted = $this->perMinute($form);

        // Zero turns both off, which is a thing a site behind its own rate limiting wants.
        if ($accepted <= 0) {
            return Limit::none();
        }

        $attempts = (int) ($form?->antispam('attempts') ?? $this->config->get('webx-inbox.antispam.attempts', 20));

        // Never below the number of submissions it lets through, or that one would be a lie.
        return Limit::perMinute(max($attempts, $accepted))->by($request->ip().'|'.$slug.'|attempts');
    }

    /** Refuses with a 429 when this address has already sent as many as the form takes. */
    public function check(Form $form, Request $request): void
    {
        $limit = $this->perMinute($form);

        if ($limit <= 0 || ! $this->limiter->tooManyAttempts($this->key($form, $request), $limit)) {
            return;
        }

        $retryAfter = $this->limiter->availableIn($this->key($form, $request));

        throw new ThrottleRequestsException('Too Many Attempts.', null, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /** One more submission that got through. */
    public function hit(Form $form, Request $request): void
    {
        if ($this->perMinute($form) > 0) {
            $this->limiter->hit($this->key($form, $request), 60);
        }
    }

    private function perMinute(?Form $form): int
    {
        return $form !== null
            ? (int) $form->antispam('throttle')
            : (int) $this->config->get('webx-inbox.antispam.throttle', 5);
    }

    private function key(Form $form, Request $request): string
    {
        return 'webx-inbox|'.$request->ip().'|'.$form->slug;
    }
}
