<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Throwable;
use WebxUi\Routing\UrlNormaliser;

/**
 * Which rule an address belongs to.
 *
 * One class for rules and for redirects on purpose. Two matchers drift — a mask that works in
 * one and not in the other is a support question nobody can answer from the screen — and there
 * is nothing about a redirect that wants a different idea of what `*` means.
 *
 * The order is exact, then mask, then regex: from the rule that names one page to the rule that
 * describes a shape. Inside a group, `priority` descending, then `id` ascending, so that two
 * rules an editor wrote for the same address always resolve the same way.
 */
final class UrlMatcher
{
    public const EXACT = 'exact';

    public const MASK = 'mask';

    public const REGEX = 'regex';

    /** Sorting weight of each kind; lower is tried first. */
    private const ORDER = [self::EXACT => 0, self::MASK => 1, self::REGEX => 2];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [self::EXACT, self::MASK, self::REGEX];
    }

    /**
     * The first row that covers the address, or null.
     *
     * @param  list<array<string, mixed>>  $rows  Already ordered by {@see self::ordered()}.
     * @return array<string, mixed>|null
     */
    public function match(string $url, array $rows): ?array
    {
        $url = UrlNormaliser::normalise($url);

        foreach ($rows as $row) {
            if (self::covers(self::text($row, 'match_type'), self::text($row, 'pattern'), $url)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The rows in the order they should be tried. Done once, when the list is compiled into
     * the cache, rather than on every hit.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function ordered(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            $kind = (self::ORDER[self::text($a, 'match_type')] ?? 9) <=> (self::ORDER[self::text($b, 'match_type')] ?? 9);

            if ($kind !== 0) {
                return $kind;
            }

            $priority = self::number($b, 'priority') <=> self::number($a, 'priority');

            return $priority !== 0 ? $priority : self::number($a, 'id') <=> self::number($b, 'id');
        });

        return $rows;
    }

    public static function covers(string $matchType, string $pattern, string $url): bool
    {
        if ($matchType === self::EXACT) {
            return UrlNormaliser::normalise($pattern) === $url;
        }

        $regex = self::regex($matchType, $pattern);

        return $regex !== null && self::safeMatch($regex, $url) === 1;
    }

    /**
     * Where a match sends the browser. For a mask or a regex the target may name what the
     * pattern captured — `/catalog/*` to `/shop/$1`.
     */
    public static function target(string $matchType, string $pattern, string $target, string $url): string
    {
        $regex = self::regex($matchType, $pattern);

        if ($regex === null) {
            return $target;
        }

        // Backslashes in the replacement are made literal; `$1` is left alone, because naming
        // what the pattern caught is the whole point of a mask redirect.
        $replaced = self::safeReplace($regex, str_replace('\\', '\\\\', $target), UrlNormaliser::normalise($url));

        return $replaced ?? $target;
    }

    /**
     * A mask compiled: `*` is a stretch without a slash, `**` is a stretch with them. Both
     * capture, so a redirect can put back what it matched.
     */
    public static function maskToRegex(string $mask): string
    {
        $quoted = preg_quote(UrlNormaliser::normalise($mask), '#');

        // `**` first — otherwise the single-segment rule eats the first half of it.
        $body = str_replace(['\*\*', '\*'], ['(.*)', '([^/]*)'], $quoted);

        return '#^'.$body.'$#u';
    }

    /**
     * Is this something `preg_match` will accept? Asked when a rule is saved, so that a
     * mistyped regular expression is a message under the field rather than a 500 on the
     * public side.
     */
    public static function isValidRegex(string $pattern): bool
    {
        return $pattern !== '' && self::safeMatch($pattern, '') !== null;
    }

    /** The pattern as a regular expression, or null when the kind has none. */
    private static function regex(string $matchType, string $pattern): ?string
    {
        return match ($matchType) {
            self::MASK => self::maskToRegex($pattern),
            // Stored the way it was written, delimiters and all. A rule saved before the
            // validator existed, or edited straight in the database, must not be able to take
            // the site down: a pattern that will not compile simply never matches.
            self::REGEX => $pattern === '' ? null : $pattern,
            default => null,
        };
    }

    /**
     * `preg_match` without the warning. A broken pattern gives null, and every caller reads
     * that as "no match" rather than as an error worth interrupting a page for.
     */
    private static function safeMatch(string $regex, string $subject): ?int
    {
        set_error_handler(static fn (): bool => true);

        try {
            $result = preg_match($regex, $subject);
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }

        return $result === false ? null : $result;
    }

    private static function safeReplace(string $regex, string $replacement, string $subject): ?string
    {
        set_error_handler(static fn (): bool => true);

        try {
            $result = preg_replace($regex, $replacement, $subject);
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }

        return is_string($result) ? $result : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function text(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function number(array $row, string $key): int
    {
        $value = $row[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
