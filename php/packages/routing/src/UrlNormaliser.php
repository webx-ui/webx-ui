<?php

declare(strict_types=1);

namespace WebxUi\Routing;

/**
 * One spelling for an address.
 *
 * `key()` is the registry's own spelling: the path alone, lower case, no slashes on either end,
 * no repeated ones in the middle, and no language prefix. It is what goes into the `path`
 * column and what a request is looked up by, so the two can never disagree about whether
 * `/Parts/` and `parts` are the same address.
 *
 * The query string is deliberately gone: it is what a rule for addresses is written against
 * (`?page=2`, `?sort=price`), not part of what an address *is*. `module-seo` keeps its own
 * `normalise()` for that, and it moves in here next to this one.
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
