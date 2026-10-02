<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Throwable;

/**
 * Addresses as the crawl compares them: absolute, without the fragment, with the scheme and host
 * lowercased and the default port dropped. The path and the query are left as written — `/About`
 * and `/about` are two addresses, and telling the site so is the point of `host.case`.
 */
final class Urls
{
    /** An address written in a page, resolved against the page (or its `<base>`), or null. */
    public static function resolve(string $base, string $value): ?string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));

        if ($value === '' || str_starts_with($value, '#')) {
            return null;
        }

        // `mailto:`, `tel:`, `javascript:`, `data:` — a scheme that is not the web's.
        if (preg_match('~^([a-z][a-z0-9+.-]*):~i', $value, $scheme) === 1 && ! in_array(strtolower($scheme[1]), ['http', 'https'], true)) {
            return null;
        }

        try {
            $resolved = UriResolver::resolve(new Uri($base), new Uri($value));
        } catch (Throwable) {
            return null;
        }

        return self::normalise((string) $resolved);
    }

    /** The comparable form of an absolute address, or null for one that is not http(s). */
    public static function normalise(string $url): ?string
    {
        try {
            $uri = new Uri(trim($url));
        } catch (Throwable) {
            return null;
        }

        $scheme = strtolower($uri->getScheme());

        if (! in_array($scheme, ['http', 'https'], true) || $uri->getHost() === '') {
            return null;
        }

        $uri = $uri->withScheme($scheme)->withHost(strtolower($uri->getHost()))->withFragment('');

        if ($uri->getPath() === '') {
            $uri = $uri->withPath('/');
        }

        return (string) $uri;
    }

    /** Written with a scheme or as `//host` — not a path. */
    public static function absolute(string $value): bool
    {
        return preg_match('~^\s*(https?:)?//~i', $value) === 1;
    }

    /** The path and query of an address: `/blog?page=2`. */
    public static function pathOf(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
