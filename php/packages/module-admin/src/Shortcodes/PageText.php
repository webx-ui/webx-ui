<?php

declare(strict_types=1);

namespace WebxUi\Admin\Shortcodes;

use Stringable;
use WebxUi\Admin\Screens\FieldTypes;

/**
 * A module's text as the site prints it: the one way every public page, card, feed and piece of
 * markup turns what an editor wrote into what a visitor reads, with the shortcodes in it.
 *
 * Why one class. The panel offers the shortcodes in a module's rich text and in the text fields a
 * screen marks (`props.shortcodes: true`); a page that printed one of those fields as stored would
 * show the visitor a bare `[phone]`. So every place that prints such a field asks here, and the
 * rule cannot drift between a product page and the card of the same product in a list.
 *
 * The record keeps the bracket, never the result: nothing here is written back, and what the
 * panel and the agents read is the text as it was typed.
 *
 * - {@see self::html()} — a rich text field for a page: its library pictures pointed at where they
 *   live now, the shortcodes replaced between the tags.
 * - {@see self::text()} — a line or a paragraph for a template: the same string when there is no
 *   shortcode in it, a {@see ShortcodeText} when there is — `{{ }}` prints both right, and so does
 *   `nl2br(e(...))`, since `e()` hands an Htmlable its HTML as it is.
 * - {@see self::plain()} and {@see self::plainFromHtml()} — text where HTML cannot go: JSON-LD,
 *   a meta description, a feed's summary, a search index.
 */
final readonly class PageText
{
    public function __construct(
        private Shortcodes $shortcodes,
        private FieldTypes $types,
    ) {}

    /** A rich text field as a page prints it — '' for nothing written. */
    public function html(mixed $stored, ?string $locale = null): string
    {
        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = $this->types->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return $this->shortcodes->htmlIn(is_string($resolved) ? $resolved : $stored);
    }

    /** A line or a paragraph an editor wrote, for a template to print. */
    public function text(string|Stringable|null $text): string|ShortcodeText|null
    {
        if ($text instanceof ShortcodeText) {
            return $text;
        }

        return $text === null ? null : $this->shortcodes->resolve((string) $text);
    }

    /** A line or a paragraph as text: every shortcode its plain rendering, nothing escaped. */
    public function plain(string|Stringable|null $text): string
    {
        return $this->shortcodes->plain($text);
    }

    /** A rich text field as text: the tags out, the entities read, the shortcodes as text. */
    public function plainFromHtml(string|Stringable|null $html): string
    {
        return $this->shortcodes->text($html);
    }
}
