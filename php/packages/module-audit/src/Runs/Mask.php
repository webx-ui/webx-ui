<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

/**
 * An address or a mask of addresses, written the way the SEO rules write them: `*` is a stretch
 * without a slash, `**` is a stretch with them. Used by the hiding rules and by the paths the
 * crawl leaves out.
 *
 * A pattern that starts with a scheme is compared with the whole address; any other with its
 * path (and its query, when the pattern has a `?`), so `/search/**` hides the search on every
 * mirror the run happened to open.
 */
final class Mask
{
    public static function matches(string $pattern, ?string $url): bool
    {
        $pattern = trim($pattern);

        // An empty pattern is the whole check — the findings of the host and of the database,
        // which have no address, included.
        if ($pattern === '' || $pattern === '**') {
            return true;
        }

        if ($url === null || $url === '') {
            return false;
        }

        $subject = preg_match('~^[a-z][a-z0-9+.-]*://~i', $pattern) === 1 ? $url : self::path($url, str_contains($pattern, '?'));

        return preg_match(self::regex($pattern), $subject) === 1;
    }

    /**
     * True when any of the patterns matches.
     *
     * @param  array<mixed>  $patterns
     */
    public static function any(array $patterns, ?string $url): bool
    {
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && trim($pattern) !== '' && self::matches($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    private static function path(string $url, bool $withQuery): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = $path === '' ? '/' : $path;
        $query = parse_url($url, PHP_URL_QUERY);

        return $withQuery && is_string($query) && $query !== '' ? $path.'?'.$query : $path;
    }

    private static function regex(string $pattern): string
    {
        $quoted = preg_quote($pattern, '~');

        // `**` first — otherwise the one-segment rule eats half of it.
        return '~^'.str_replace(['\*\*', '\*'], ['.*', '[^/]*'], $quoted).'$~u';
    }
}
