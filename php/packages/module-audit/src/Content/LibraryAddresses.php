<?php

declare(strict_types=1);

namespace WebxUi\Audit\Content;

/**
 * Takes out of a field the addresses the site itself never prints: the `src` (or `href`) of a
 * tag that carries a library key in `data-wx-path`.
 *
 * Such an address is a cache. The panel keeps it beside the key so the editor has something to
 * draw, and it is the address of the day the paragraph was written; the site works it out again
 * from the key on every read. A site that moved from its stand to its real domain therefore has
 * the stand's host in every picture of every page it wrote there — and none of it on a single
 * page. Reporting those would be a list of links to a stand that nobody can find on the site and
 * that saving the paragraph again quietly fixes; the person reading the audit would be told to
 * hunt for something that is not there.
 *
 * Only the cached attribute goes: a link without a key — typed in, pasted from a stand — is
 * printed as written and still found. Works on HTML as stored and on HTML inside JSON, where the
 * quotes arrive escaped (`src=\"…\"`), which is how a block keeps its rich text.
 */
final class LibraryAddresses
{
    private const KEY = 'data-wx-path';

    public static function strip(string $text): string
    {
        if (! str_contains($text, self::KEY)) {
            return $text;
        }

        return (string) preg_replace_callback(
            '~<[a-z][a-z0-9]*\b[^<>]*>~i',
            static function (array $tag): string {
                if (! str_contains($tag[0], self::KEY)) {
                    return $tag[0];
                }

                // The quote is matched together with its backslash, so the value ends at the
                // escaped quote that closes it and not at the first plain one inside the JSON.
                return (string) preg_replace('~\s(?:src|href)=(\\\\?["\']).*?\1~is', '', $tag[0]);
            },
            $text,
        );
    }
}
