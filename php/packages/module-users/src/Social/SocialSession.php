<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Date;

/**
 * What a sign-in through a social network keeps in the visitor's session (spec §9.4, §9.5, §9.7).
 *
 * - **The trip** to the provider: the module's `state` (32 random bytes), the provider, the
 *   intent — `sign-in` or `link` —, the page to go back to and the language of the page. Taken
 *   once on the way back: a second callback with the same `state` finds nothing.
 * - **The profile waiting** for the visitor, fifteen minutes: for «Almost done» (no address, a
 *   field the site requires) or for «sign in with your password and it is linked» — then with the
 *   id of the account the address belongs to, and only that account's sign-in links it.
 */
final class SocialSession
{
    public const string TRIP = 'webx-users.social.trip';

    public const string PENDING = 'webx-users.social.pending';

    /** A sentence for the next page: the sign-in, the security of the account. */
    public const string NOTICE = 'webx-users.social.notice';

    public const string SIGN_IN = 'sign-in';

    public const string LINK = 'link';

    /** How long the trip and the waiting profile live. */
    public const int MINUTES = 15;

    public function __construct(private readonly Session $session) {}

    public function start(string $provider, string $intent, ?string $back, string $locale): string
    {
        $state = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->session->put(self::TRIP, [
            'state' => $state,
            'provider' => $provider,
            'intent' => $intent,
            'back' => $back,
            'locale' => $locale,
            'until' => Date::now()->addMinutes(self::MINUTES)->getTimestamp(),
        ]);

        return $state;
    }

    /**
     * The trip the provider sent the visitor back from, if the `state` is the one given to this
     * session for this provider and is not out of date; gone either way.
     *
     * @return array{provider: string, intent: string, back: ?string, locale: string}|null
     */
    public function finish(string $provider, mixed $state): ?array
    {
        $trip = $this->session->pull(self::TRIP);

        if (! is_array($trip) || ! is_string($state) || ! is_string($trip['state'] ?? null)
            || ! hash_equals($trip['state'], $state)
            || ($trip['provider'] ?? null) !== $provider
            || (int) ($trip['until'] ?? 0) < Date::now()->getTimestamp()) {
            return null;
        }

        return [
            'provider' => $provider,
            'intent' => ($trip['intent'] ?? null) === self::LINK ? self::LINK : self::SIGN_IN,
            'back' => is_string($trip['back'] ?? null) ? $trip['back'] : null,
            'locale' => is_string($trip['locale'] ?? null) ? $trip['locale'] : '',
        ];
    }

    /** Keep the profile for «Almost done» (`$account` null) or for the password of `$account`. */
    public function wait(string $provider, SocialProfile $profile, ?int $account, ?string $back): void
    {
        $this->session->put(self::PENDING, [
            'provider' => $provider,
            'profile' => $profile->toArray(),
            'account' => $account,
            'back' => $back,
            'until' => Date::now()->addMinutes(self::MINUTES)->getTimestamp(),
        ]);
    }

    /** @return array{provider: string, profile: SocialProfile, account: ?int, back: ?string}|null */
    public function waiting(): ?array
    {
        $pending = $this->session->get(self::PENDING);

        if (! is_array($pending) || ! is_string($pending['provider'] ?? null) || ! is_array($pending['profile'] ?? null)) {
            return null;
        }

        if ((int) ($pending['until'] ?? 0) < Date::now()->getTimestamp()) {
            $this->forget();

            return null;
        }

        return [
            'provider' => $pending['provider'],
            'profile' => SocialProfile::fromArray($pending['profile']),
            'account' => is_int($pending['account'] ?? null) ? $pending['account'] : null,
            'back' => is_string($pending['back'] ?? null) ? $pending['back'] : null,
        ];
    }

    public function forget(): void
    {
        $this->session->forget(self::PENDING);
    }

    public function notice(string $words): void
    {
        $this->session->flash(self::NOTICE, $words);
    }
}
