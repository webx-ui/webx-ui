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

    /** @param  list<array{string, int}>  $limits */
    private function refuseOver(array $limits, string $field): void
    {
        foreach ($limits as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                $seconds = max(1, $this->limiter->availableIn($key));

                throw ValidationException::withMessages([
                    $field => trans('webx-users::auth.throttled', ['seconds' => $seconds]),
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
