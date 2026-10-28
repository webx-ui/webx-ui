<?php

declare(strict_types=1);

namespace WebxUi\Users;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Validation\ValidationException;

/**
 * The limits of spec §15 that a form must answer in words: a 429 that names the field and how long
 * to wait, rather than the `throttle` middleware's bare page — the form shows it where the
 * visitor looks.
 */
final readonly class Throttle
{
    public function __construct(
        private RateLimiter $limiter,
        private Config $config,
    ) {}

    /** Failed sign-ins: per address and IP, and per IP whatever the address — a minute each. */
    public function signIn(string $email, string $ip): void
    {
        $this->refuseOver([
            [self::pair($email, $ip), $this->limit('sign_in', 5)],
            ['webx-users:sign-in-ip:'.$ip, $this->limit('sign_in_ip', 30)],
        ], 'email');
    }

    public function signInFailed(string $email, string $ip): void
    {
        $this->limiter->hit(self::pair($email, $ip), 60);
        $this->limiter->hit('webx-users:sign-in-ip:'.$ip, 60);
    }

    public function signedIn(string $email, string $ip): void
    {
        $this->limiter->clear(self::pair($email, $ip));
    }

    /**
     * Registrations per IP an hour, every attempt counted: the page says whether an address is
     * taken (decision 15), and this is what keeps that from being a list of the customers.
     */
    public function register(string $ip): void
    {
        $key = 'webx-users:register:'.$ip;

        $this->refuseOver([[$key, $this->limit('register', 5)]], 'email');
        $this->limiter->hit($key, 3600);
    }

    /**
     * Requests of a reset link: per address and IP, and per IP — an hour each. Answers whether a
     * letter may leave; over the limit none does, and the page says what it always says (§7).
     */
    public function forgot(string $email, string $ip): bool
    {
        $limits = [
            ['webx-users:forgot:'.hash('sha256', $email.'|'.$ip), $this->limit('forgot', 3)],
            ['webx-users:forgot-ip:'.$ip, $this->limit('forgot_ip', 20)],
        ];

        foreach ($limits as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                return false;
            }
        }

        foreach ($limits as [$key]) {
            $this->limiter->hit($key, 3600);
        }

        return true;
    }

    /** New passwords sent from the page of a link, per IP a minute: the token is not guessed. */
    public function reset(string $ip): void
    {
        $key = 'webx-users:reset:'.$ip;

        $this->refuseOver([[$key, $this->limit('reset', 10)]], 'password');
        $this->limiter->hit($key, 60);
    }

    /** The letter of confirmation once more: once a minute and five times an hour per visitor (§8.2). */
    public function verify(int $visitor): void
    {
        $minute = 'webx-users:verify-minute:'.$visitor;
        $hour = 'webx-users:verify-hour:'.$visitor;

        $this->refuseOver([[$minute, $this->limit('verify_minute', 1)], [$hour, $this->limit('verify_hour', 5)]], 'resend', 'webx-users::auth.verify.throttled');
        $this->limiter->hit($minute, 60);
        $this->limiter->hit($hour, 3600);
    }

    /** Letters to a new address, per visitor an hour (§8.5). */
    public function emailChange(int $visitor): void
    {
        $key = 'webx-users:email-change:'.$visitor;

        $this->refuseOver([[$key, $this->limit('email_change', 3)]], 'email', 'webx-users::auth.verify.throttled');
        $this->limiter->hit($key, 3600);
    }

    /** @param  list<array{string, int}>  $limits */
    private function refuseOver(array $limits, string $field, string $words = 'webx-users::auth.throttled'): void
    {
        foreach ($limits as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                $seconds = max(1, $this->limiter->availableIn($key));

                throw ValidationException::withMessages([
                    $field => trans($words, ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
                ])->status(429);
            }
        }
    }

    private function limit(string $name, int $default): int
    {
        $value = $this->config->get('webx-users.throttle.'.$name);

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }

    private static function pair(string $email, string $ip): string
    {
        return 'webx-users:sign-in:'.hash('sha256', $email.'|'.$ip);
    }
}
