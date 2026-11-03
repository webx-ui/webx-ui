<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use Normalizer as Intl;

/**
 * The key a geographic name is matched and searched by (§4.2 of the delivery spec).
 *
 * Deliberately without language rules — no transliteration, no folding of letters that look
 * alike in one language: the key has to behave the same for any alphabet, and what one language
 * spells two ways is an alias row, not a rule here.
 */
final class Normalizer
{
    /** The longest key a column holds: `varchar(191)`, the most a utf8mb4 index takes whole. */
    public const int MAX_LENGTH = 191;

    /** Every apostrophe a carrier or a keyboard may send, folded into the plain one. */
    private const array APOSTROPHES = ["'", '’', '‘', 'ʼ', 'ʻ', '`', '´', '′'];

    /** Hyphens, dashes and the minus sign — one `-`. */
    private const array DASHES = ['‐', '‑', '‒', '–', '—', '―', '−', '﹣', '－'];

    /** Quotes and dots carry no meaning in a name: "St. Town" and "St Town" are one place. */
    private const array DROPPED = ['"', '«', '»', '„', '“', '”', '‟', '‹', '›', '.', '·'];

    public static function key(string $name): string
    {
        $normalized = Intl::normalize($name, Intl::FORM_KC);
        $key = mb_strtolower($normalized === false ? $name : $normalized, 'UTF-8');

        $key = str_replace(self::APOSTROPHES, "'", $key);
        $key = str_replace(self::DASHES, '-', $key);
        $key = str_replace(self::DROPPED, '', $key);
        $key = trim((string) preg_replace('/\s+/u', ' ', $key));

        return mb_substr($key, 0, self::MAX_LENGTH, 'UTF-8');
    }

    /**
     * The tails of a name of several words, each starting at a later word: "lower town d" gives
     * "town d" and "d". Stored as `word` rows, they let a prefix search find a locality by any
     * of its words without a `%q%` the index cannot take (§4.5).
     *
     * @return list<string>
     */
    public static function tails(string $key): array
    {
        $tails = [];
        $offset = 0;

        while (preg_match('/[ -]+/u', $key, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $tail = substr($key, $offset);

            if ($tail !== '') {
                $tails[] = $tail;
            }
        }

        return $tails;
    }

    /**
     * A key made safe for `LIKE ? ESCAPE '!'`: its own `%` and `_` are letters, not wildcards.
     * `!` rather than a backslash, which MySQL reads inside a literal and sqlite does not.
     */
    public static function likePrefix(string $key): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $key).'%';
    }
}
