<?php

declare(strict_types=1);

namespace WebxUi\Press\Rendering;

use Carbon\CarbonInterface;
use WebxUi\Press\Models\Article;

/**
 * When an article ran, in words (§4.5) — one helper, so the page, the cards and an agent's
 * catalogue all say it alike.
 *
 *     day       August 12, 2023  · 12 августа 2023
 *     month     August 2023      · август 2023
 *     year      2023
 *     no date   nothing
 *
 * A day is the language's own long date (`LL`), so the order of its parts is right everywhere —
 * "12. August 2023" in German — and the month takes the case a date takes ("августа"). A month on
 * its own is named instead ("август"): Carbon's languages that tell the two apart pick the case by
 * whether a day stands before the month in the format, and in `MMMM YYYY` none does.
 */
final class When
{
    /**
     * What a language's long date adds after the year and a date in a list of articles does not
     * need: "12 августа 2023 г." is a document, "12 августа 2023" is a byline.
     */
    private const YEAR_WORDS = ['ru' => ' г.', 'be' => ' г.'];

    public static function of(Article $article, string $locale): string
    {
        return self::format($article->published_on, $article->date_precision, $locale);
    }

    public static function format(?CarbonInterface $date, ?string $precision, string $locale): string
    {
        if ($date === null) {
            return '';
        }

        $date = $date->copy()->locale($locale);

        return match ($precision) {
            Article::YEAR => $date->isoFormat('YYYY'),
            Article::MONTH => $date->isoFormat('MMMM YYYY'),
            default => self::day($date, $locale),
        };
    }

    private static function day(CarbonInterface $date, string $locale): string
    {
        $day = $date->isoFormat('LL');
        $words = self::YEAR_WORDS[strtolower(substr($locale, 0, 2))] ?? null;

        return $words !== null && str_ends_with($day, $words) ? substr($day, 0, -strlen($words)) : $day;
    }
}
