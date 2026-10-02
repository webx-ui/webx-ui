<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

/**
 * The three levels of §5, and what each weighs.
 *
 * The weights answer the open question of §12 the way the spec proposes: a check counts once,
 * not once per address, so a hundred pictures without `alt` cannot outweigh a `noindex` on the
 * home page.
 */
final class Severity
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const NOTICE = 'notice';

    /** Worst first — the order the panel lists them in. */
    public const ALL = [self::ERROR, self::WARNING, self::NOTICE];

    private const WEIGHTS = [self::ERROR => 10, self::WARNING => 3, self::NOTICE => 1];

    public static function weight(string $severity): int
    {
        return self::WEIGHTS[$severity] ?? 0;
    }

    /** Whether `$severity` is at least as bad as `$threshold`. */
    public static function reaches(string $severity, string $threshold): bool
    {
        return self::weight($severity) >= self::weight($threshold) && self::weight($threshold) > 0;
    }

    public static function valid(string $severity): bool
    {
        return array_key_exists($severity, self::WEIGHTS);
    }
}
