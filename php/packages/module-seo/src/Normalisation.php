<?php

declare(strict_types=1);

namespace WebxUi\Seo;

use Illuminate\Contracts\Container\Container;
use WebxUi\Settings\Settings;

/**
 * The address normalisation settings of the SEO tab, read in one place: what
 * {@see Http\Middleware\NormaliseAddress} obeys and what the audit's fixes turn on.
 *
 * Every part is off until somebody turns it on. A site that already answers `/About` as a page
 * of its own has links to it out there, and a 301 it did not ask for is not a fix.
 */
final readonly class Normalisation
{
    public const HOST = 'seo.normalise-host';

    public const HTTPS = 'seo.normalise-https';

    public const SLASHES = 'seo.normalise-slashes';

    public const INDEX = 'seo.normalise-index';

    public const TRAILING = 'seo.normalise-trailing';

    public const LOWERCASE = 'seo.normalise-case';

    /** `seo.normalise-host`: the main mirror. */
    public const WWW = 'www';

    public const BARE = 'bare';

    /** `seo.normalise-trailing`: the policy for the slash at the end. */
    public const STRIP = 'strip';

    public const ADD = 'add';

    public function __construct(private Container $container) {}

    public function host(): string
    {
        $value = $this->value(self::HOST);

        return in_array($value, [self::WWW, self::BARE], true) ? $value : '';
    }

    public function https(): bool
    {
        return (bool) $this->value(self::HTTPS);
    }

    public function slashes(): bool
    {
        return (bool) $this->value(self::SLASHES);
    }

    public function index(): bool
    {
        return (bool) $this->value(self::INDEX);
    }

    public function trailing(): string
    {
        $value = $this->value(self::TRAILING);

        return in_array($value, [self::STRIP, self::ADD], true) ? $value : '';
    }

    public function lowercase(): bool
    {
        return (bool) $this->value(self::LOWERCASE);
    }

    /** Whether anything is on — the middleware's way out on every request when nothing is. */
    public function any(): bool
    {
        return $this->host() !== '' || $this->https() || $this->slashes() || $this->index()
            || $this->trailing() !== '' || $this->lowercase();
    }

    /** Turns one part on — what a fix does. */
    public function set(string $key, string|bool $value): void
    {
        if ($this->container->bound(Settings::class)) {
            $this->container->make(Settings::class)->save([$key => $value]);
        }
    }

    public function value(string $key): mixed
    {
        if (! $this->container->bound(Settings::class)) {
            return null;
        }

        return $this->container->make(Settings::class)->get($key);
    }
}
