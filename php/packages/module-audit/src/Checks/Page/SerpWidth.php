<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Normalizer;

/**
 * How wide a line is in the search results, in pixels — which is what the results cut a title
 * by, not characters. The font is the results' own, not the site's: Arial (metrically the same as
 * Helvetica, whose widths these are, in thousandths of the font size). Cyrillic has its own table;
 * accented Latin is measured as its base letter; Chinese, Japanese and Korean are a full em;
 * anything else is an average letter. An estimate within a few pixels — enough to tell a title
 * that fits from one that is cut.
 */
final class SerpWidth
{
    private const LATIN = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556,
        '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
        'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
        'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
        'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556,
        '`' => 333, 'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
        'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556,
        'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722,
        'x' => 500, 'y' => 500, 'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        // Typography a title is likely to have.
        '–' => 556, '—' => 1000, '…' => 1000, '«' => 556, '»' => 556, '“' => 333, '”' => 333, '‘' => 222,
        '’' => 222, '·' => 278, '•' => 350, '№' => 1073, 'ß' => 611, 'ł' => 222, 'Ł' => 556,
        'ı' => 278, 'ø' => 611, 'Ø' => 778, 'æ' => 889, 'Æ' => 1000, 'œ' => 944, 'Œ' => 1000, '€' => 556,
    ];

    private const CYRILLIC = [
        'а' => 556, 'б' => 573, 'в' => 531, 'г' => 365, 'д' => 583, 'е' => 556, 'ё' => 556, 'ж' => 669,
        'з' => 458, 'и' => 559, 'й' => 559, 'к' => 438, 'л' => 583, 'м' => 688, 'н' => 552, 'о' => 556,
        'п' => 542, 'р' => 556, 'с' => 500, 'т' => 458, 'у' => 500, 'ф' => 823, 'х' => 500, 'ц' => 573,
        'ч' => 521, 'ш' => 802, 'щ' => 823, 'ъ' => 625, 'ы' => 719, 'ь' => 521, 'э' => 510, 'ю' => 750,
        'я' => 542, 'і' => 222, 'ї' => 278, 'є' => 510, 'ґ' => 365,
        'А' => 667, 'Б' => 656, 'В' => 667, 'Г' => 542, 'Д' => 677, 'Е' => 667, 'Ё' => 667, 'Ж' => 923,
        'З' => 604, 'И' => 719, 'Й' => 719, 'К' => 583, 'Л' => 656, 'М' => 833, 'Н' => 722, 'О' => 778,
        'П' => 719, 'Р' => 667, 'С' => 722, 'Т' => 611, 'У' => 635, 'Ф' => 760, 'Х' => 667, 'Ц' => 740,
        'Ч' => 667, 'Ш' => 917, 'Щ' => 938, 'Ъ' => 792, 'Ы' => 885, 'Ь' => 656, 'Э' => 719, 'Ю' => 1010,
        'Я' => 722, 'І' => 278, 'Ї' => 278, 'Є' => 719, 'Ґ' => 542,
    ];

    /** The width of `$text` set at `$size` pixels, rounded to a pixel. */
    public static function of(string $text, int $size): int
    {
        $total = 0;

        foreach (mb_str_split($text) as $char) {
            $total += self::char($char);
        }

        return (int) round($total * $size / 1000);
    }

    private static function char(string $char): int
    {
        if (isset(self::LATIN[$char])) {
            return self::LATIN[$char];
        }

        if (isset(self::CYRILLIC[$char])) {
            return self::CYRILLIC[$char];
        }

        // `é` is `e` with an accent on top: as wide as the `e`.
        if (class_exists(Normalizer::class)) {
            $base = mb_substr((string) Normalizer::normalize($char, Normalizer::FORM_D), 0, 1);

            if ($base !== $char && isset(self::LATIN[$base])) {
                return self::LATIN[$base];
            }
        }

        if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $char) === 1) {
            return 1000;
        }

        return preg_match('/\p{Lu}/u', $char) === 1 ? 667 : 556;
    }
}
