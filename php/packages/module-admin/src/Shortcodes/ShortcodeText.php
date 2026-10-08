<?php

declare(strict_types=1);

namespace WebxUi\Admin\Shortcodes;

use Illuminate\Contracts\Support\Htmlable;
use JsonSerializable;
use Stringable;

/**
 * An editor's text with shortcodes in it, resolved: what a block's template is handed for a text
 * field that holds one.
 *
 * Htmlable so that `{{ $heading }}` prints the HTML without escaping it again — the escaping has
 * already happened, to the editor's text and not to the shortcode's output — and `{!! $heading !!}`
 * prints the same. A string to everything that wants one. Where the template needs text — an
 * attribute, a `@json` for a script — `plain()` is the text, and `@shortcodesPlain($heading)`
 * says the same thing without knowing which of the two the field is.
 */
final readonly class ShortcodeText implements Htmlable, JsonSerializable, Stringable
{
    public function __construct(
        private string $text,
        private string $html,
        private string $plain,
    ) {}

    public function toHtml(): string
    {
        return $this->html;
    }

    /** The text with every shortcode as its plain rendering, unescaped. */
    public function plain(): string
    {
        return $this->plain;
    }

    /** The text as the editor typed it, brackets and all. */
    public function text(): string
    {
        return $this->text;
    }

    public function __toString(): string
    {
        return $this->html;
    }

    public function jsonSerialize(): string
    {
        return $this->plain;
    }
}
