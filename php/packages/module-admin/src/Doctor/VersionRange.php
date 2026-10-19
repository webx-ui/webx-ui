<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor;

/**
 * The lowest version a npm range will accept.
 *
 * Enough of semver to answer one question — is what the site asks for older than what the
 * server needs — and deliberately not more. Comparing ranges properly means implementing
 * semver's range algebra, and the answer would still be the same one: `^0.19.0` is what the
 * package needs, `^0.18.0` is what the site asks for, and npm is free to install 0.18.2 under
 * it. The caret on a zero major pins the minor, so a site that asks a minor too low never
 * reaches the version it needs — and finds out as `[MISSING_EXPORT]` in a build (CLAUDE.md §5).
 *
 * A range that names a place rather than a version — `file:`, `link:`, `workspace:`, a git URL
 * — has no floor and gets none: a site pointing at a checkout has answered this question for
 * itself, and that is exactly how the monorepo's own demo runs.
 */
final class VersionRange
{
    /** The lowest version the range accepts, as x.y.z, or null when there is no saying. */
    public static function floor(string $range): ?string
    {
        $range = trim($range);

        if ($range === '' || preg_match('#^[a-z]+[:/]#i', $range) === 1 || str_contains($range, '://')) {
            return null;
        }

        // The first comparator is the low one in every range anybody writes: `^1.2.0`,
        // `>=1.2 <2`, `1.2.x`. An `||` union takes its floor from the first branch too.
        if (preg_match('/(\d+)(?:\.(\d+|[x*]))?(?:\.(\d+|[x*]))?/', $range, $parts) !== 1) {
            return null;
        }

        $number = static fn (?string $part): int => is_numeric($part) ? (int) $part : 0;

        return $parts[1].'.'.$number($parts[2] ?? null).'.'.$number($parts[3] ?? null);
    }

    /** Whether `$candidate` is a version below what `$required` will accept. */
    public static function below(string $candidate, string $required): bool
    {
        $floor = self::floor($required);
        $theirs = self::floor($candidate);

        return $floor !== null && $theirs !== null && version_compare($theirs, $floor, '<');
    }

    /**
     * Whether npm may install `$version` under the range at all.
     *
     * The other half of the question `below()` asks: `^3.4.0` starts below 3.5.0 and still lets
     * npm reach it, `^0.30.0` does not let it reach 0.31.0 — the caret on a zero major pins the
     * minor. Caret, tilde, x-ranges, bare versions and an upper `<` bound are read; a union, or
     * a range that names a place, is taken to admit it, because rewriting what is not understood
     * is worse than leaving it.
     */
    public static function admits(string $range, string $version): bool
    {
        $range = trim($range);
        $floor = self::floor($range);

        if ($floor === null || str_contains($range, '||')) {
            return true;
        }

        if (version_compare($version, $floor, '<')) {
            return false;
        }

        [$major, $minor, $patch] = array_map(intval(...), explode('.', $floor));

        if (preg_match('/<\s*=?\s*v?(\d+(?:\.\d+){0,2})/', $range, $upper) === 1) {
            return version_compare($version, (string) self::floor($upper[1]), str_contains($upper[0], '=') ? '<=' : '<');
        }

        if (str_contains($range, '>')) {
            return true;
        }

        $ceiling = match (true) {
            str_starts_with($range, '^') && $major > 0 => ($major + 1).'.0.0',
            str_starts_with($range, '^') && $minor > 0 => '0.'.($minor + 1).'.0',
            str_starts_with($range, '^') => '0.0.'.($patch + 1),
            str_starts_with($range, '~') => "{$major}.".($minor + 1).'.0',
            // `1`, `1.x` — the major; `1.2`, `1.2.x` — the minor; `1.2.3` — that version.
            preg_match('/^=?v?\d+(?:\.[x*])*$/i', $range) === 1 => ($major + 1).'.0.0',
            preg_match('/^=?v?\d+\.\d+(?:\.[x*])?$/i', $range) === 1 => "{$major}.".($minor + 1).'.0',
            default => null,
        };

        return $ceiling === null
            ? version_compare($version, $floor, '==')
            : version_compare($version, $ceiling, '<');
    }
}
