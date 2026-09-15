<?php

declare(strict_types=1);

namespace WebxUi\Routing;

/**
 * One spelling for an address, for both halves of the system.
 *
 * There are two spellings here because there are two questions, and conflating them would break
 * one of the two:
 *
 * `key()` is the registry's own spelling — the path alone, lower case, no slashes on either end,
 * no repeated ones in the middle, no query, no language prefix. It is what goes into the `path`
 * column and what a request is looked up by, so the two can never disagree about whether
 * `/Parts/` and `parts` are the same address.
 *
 * `normalise()` is what a rule written by hand is compared against: it keeps the leading slash
 * and the query string, and it does not touch the case. The query stays because pages of filters
 * and pagination — `?page=2`, `?sort=price` — are half of what rules for addresses are written
 * for, and dropping it would quietly make one rule cover a hundred different pages. It lived in
 * `module-seo` until routing appeared; one spelling for both halves is why it moved here rather
 * than being copied.
 */
final class UrlNormaliser
{
    /** The site root is the empty string, never `/`: two names for it would be chosen two ways. */
    public static function key(string $path): string
    {
        $path = trim($path);

        // An address somebody copied out of the browser, complete with scheme and host.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            $path = parse_url($path, PHP_URL_PATH) ?: '';
        }

        [$path] = array_pad(explode('?', (string) $path, 2), 2, null);
        [$path] = array_pad(explode('#', (string) $path, 2), 2, null);

        $path = (string) preg_replace('#/+#', '/', (string) $path);
        $path = trim($path, '/');

        return mb_strtolower($path, 'UTF-8');
    }

    /**
     * The spelling a rule for addresses is written in: path and query, leading slash kept.
     *
     * What survives: the path and the query string. What does not: the scheme, the host, the
     * fragment, and a trailing slash on anything but the root.
     */
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

    /**
     * Glue segments into a path, dropping the empty ones.
     *
     * A tree whose root has no slug of its own, or a prefix that a project configured away,
     * would otherwise leave `//` in the middle of an address.
     */
    public static function join(string ...$segments): string
    {
        $segments = array_filter(array_map(self::key(...), $segments), static fn (string $segment): bool => $segment !== '');

        return implode('/', $segments);
    }
}
