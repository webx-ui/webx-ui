<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

/**
 * How long a recipe takes, the way a reader says it: `45 min`, `1 h 15 min`, `2 h`.
 *
 * The number stands where the language puts it, with nothing to agree with it — no plural forms
 * to get wrong on "1" (CLAUDE.md §4 on `:count`).
 */
final class Duration
{
    public static function format(int $minutes, ?string $locale = null): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return (string) trans('webx-recipes::site.minutes', ['count' => $rest], $locale);
        }

        if ($rest === 0) {
            return (string) trans('webx-recipes::site.hours', ['hours' => $hours], $locale);
        }

        return (string) trans('webx-recipes::site.hours-minutes', ['hours' => $hours, 'minutes' => $rest], $locale);
    }
}
