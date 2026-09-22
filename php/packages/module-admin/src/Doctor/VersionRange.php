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
}
