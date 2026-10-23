<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Testing;

use PHPUnit\Framework\Assert;

/**
 * HTML equal up to the whitespace between tags (COMPOSITIONS §10): the measure a view turned into a
 * composition is held to. The leaves keep their conditions, and an `@if` that was indented inside
 * the old view leaves its newline and indent elsewhere once it is a file of its own — the page is
 * the same, the bytes are not. Only `layout` is held to the bytes, by its golden file.
 *
 * Whitespace inside text is kept, so a space lost between two words — or between a word and a
 * tag — still fails; whitespace that is all there is between `>` and `<` is not, `<pre>` included.
 * The indent between a tag and the text next to it is a space however long it is: an `@else` one
 * level shallower gives `<address>` its text with four spaces fewer, not without its space.
 *
 *     $this->assertSameHtml($before, $this->get('/blog/a')->getContent());
 */
trait ComparesHtml
{
    public static function assertSameHtml(string $expected, string $actual, string $message = ''): void
    {
        Assert::assertSame(self::spacelessHtml($expected), self::spacelessHtml($actual), $message !== '' ? $message : 'The HTML differs beyond the whitespace between tags.');
    }

    /** The HTML without the whitespace between tags and around the whole, the indent at a tag as one space. */
    public static function spacelessHtml(string $html): string
    {
        $html = (string) preg_replace('/>\s+</', '><', $html);

        return trim((string) preg_replace(['/>\s+(?=[^\s<])/', '/(?<=[^\s>])\s+</'], ['> ', ' <'], $html));
    }
}
