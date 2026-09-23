<?php

declare(strict_types=1);

namespace WebxUi\Menu\Rendering;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Addresses reduced to the one thing highlighting compares: a path on this site.
 *
 * An absolute address to our own host counts — an editor who pasted a whole URL out of the
 * browser meant the page, not the spelling — and one to anybody else's is `null`, which is how
 * an external item ends up never highlighted. So is `mailto:` and `tel:`: they are addresses,
 * they are simply not somewhere a visitor can be standing.
 */
final class SitePath
{
    /** The path part of an address on this site, or null when it points somewhere else. */
    public static function of(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if ($parts === false) {
            return null;
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : null;

        if ($scheme !== null && ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : null;

        if ($host !== null && ! self::isOurs($host)) {
            return null;
        }

        // An item that is nothing but an anchor points at the page it is printed on, so there
        // is no path of its own to compare — and `#top` being active everywhere would be true
        // and useless.
        if (! isset($parts['path']) && $host === null) {
            return null;
        }

        return self::normalise((string) ($parts['path'] ?? ''));
    }

    /** Where the visitor is standing, in the same spelling. */
    public static function current(?Request $request = null): string
    {
        $request ??= Container::getInstance()->make('request');

        return self::normalise($request->path());
    }

    /** Whether a host is one of ours — the one being served, or the one configured. */
    private static function isOurs(string $host): bool
    {
        foreach ([URL::to('/'), (string) config('app.url')] as $ours) {
            $known = parse_url($ours, PHP_URL_HOST);

            if (is_string($known) && strtolower($known) === $host) {
                return true;
            }
        }

        return false;
    }

    /** No leading or trailing slash, and the front page is the empty string. */
    private static function normalise(string $path): string
    {
        return trim(rawurldecode($path), '/');
    }
}
