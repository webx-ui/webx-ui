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

    /**
     * Every part, as the keys of the settings they are saved under. They are the stand's own: a
     * snapshot restore keeps the target's values ({@see \WebxUi\Admin\Snapshots\SnapshotTables::preserve()}).
     */
    public const KEYS = [self::HOST, self::HTTPS, self::SLASHES, self::INDEX, self::TRAILING, self::LOWERCASE];

    /** `seo.normalise-host`: the main mirror. */
    public const WWW = 'www';

    public const BARE = 'bare';

    /**
     * `seo.normalise-trailing`: the slash at the end goes. The only policy: the registry keys
     * its addresses without one and every link the site prints is written so, and a policy of
     * adding it redirected each of those links once more — and looped against the resolver. A
     * site that saved `add` before is treated as having chosen nothing.
     */
    public const STRIP = 'strip';

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

        return $value === self::STRIP ? $value : '';
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

    /**
     * An address of the registry as these settings spell it: slashes collapsed, lower case, no
     * slash at the end. A part somebody saved is obeyed either way — «keep as it is» keeps it;
     * a part nobody has saved yet does what the registry always did, so a site that never opened
     * the tab is not suddenly answering `/About/` as a page of its own. A file keeps its case and
     * its name: `/files/Report.PDF` is somebody's upload.
     *
     * One function for both who redirect — {@see Http\Middleware\NormaliseAddress} and the
     * resolver, through {@see SeoSpelling} — so the one 301 the middleware sends lands on what
     * the resolver accepts, and the resolver never sends a second.
     */
    public function path(string $path): string
    {
        if ($this->saved(self::SLASHES) ? $this->slashes() : true) {
            $path = (string) preg_replace('~/{2,}~', '/', $path);
        }

        $file = preg_match('~/[^/]+\.[a-z0-9]{1,5}$~i', $path) === 1;

        if (($this->saved(self::LOWERCASE) ? $this->lowercase() : true) && ! $file) {
            // Only the letters a–z: an encoded `%D0%9F` stays the byte it names.
            $path = (string) preg_replace_callback('~(%[0-9A-Fa-f]{2})|[A-Z]+~', static fn (array $match): string => ($match[1] ?? '') !== '' ? $match[1] : strtolower($match[0]), $path);
        }

        if (($this->saved(self::TRAILING) ? $this->trailing() === self::STRIP : true) && $path !== '/' && ! $file) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    /** Whether somebody has saved this part — on, off or «keep» — rather than never touched it. */
    public function saved(string $key): bool
    {
        return $this->container->bound(Settings::class)
            && array_key_exists($key, $this->container->make(Settings::class)->raw());
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
