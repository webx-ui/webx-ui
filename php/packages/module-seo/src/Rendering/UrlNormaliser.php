<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

/**
 * One spelling for an address, so that a rule written by hand and a request arriving from a
 * browser are compared as the same thing.
 *
 * What survives: the path and the query string. What does not: the scheme, the host, the
 * fragment, and a trailing slash on anything but the root. The query stays because pages of
 * filters and pagination — `?page=2`, `?sort=price` — are half of what rules for addresses are
 * written for, and dropping it would quietly make one rule cover a hundred different pages.
 */
final class UrlNormaliser
{
    public static function normalise(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '/';
        }

        // An absolute address written into a rule by somebody who copied it from the browser.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1) {
            $parts = parse_url($url);
            $url = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        // The fragment never reaches the server anyway; a rule that carries one would simply
        // never match.
        $url = (string) preg_replace('/#.*$/', '', $url);

        [$path, $query] = array_pad(explode('?', $url, 2), 2, null);

        $path = '/'.ltrim((string) $path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $query === null || $query === '' ? $path : $path.'?'.$query;
    }
}
