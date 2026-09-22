<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor;

/**
 * Paths as somebody reading a report wants to see them.
 *
 * Laravel builds them out of `base_path()` and forward slashes at the same time, so a path on
 * Windows arrives with both separators in it — `…\storage\app/public` — and looks like a bug in
 * the line that printed it. Inside the application they are shortened to what a person would
 * type; outside it they stay absolute, because that is the fact worth reporting.
 */
final class Paths
{
    public static function short(string $path, string $base): string
    {
        $path = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', $base), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
