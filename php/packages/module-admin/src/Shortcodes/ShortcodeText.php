<?php

declare(strict_types=1);

namespace WebxUi\Admin\Shortcodes;

use Closure;
use Illuminate\Container\Container;
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
 *
 * What a string function does to it is the trap: `rtrim($heading, '.')` works on the HTML,
 * returns a string, and `{{ }}` escapes that string a second time. The methods below change the
 * text the editor typed and resolve it again, so the result is still one of these —
 * `{{ wx_text($heading)->trimEnd('.') }}` for a field that may be either kind.
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

    /**
     * The typed text changed by `$change` and resolved again. The change sees the brackets, not
     * what they print: trimming a full stop off `Call [phone].` leaves the phone number whole,
     * and a `[dot]` at the end is the site's accent, not something the editor typed.
     *
     * @param  Closure(string): string  $change
     */
    public function map(Closure $change): self
    {
        return Container::getInstance()->make(Shortcodes::class)->wrap($change($this->text));
    }

    /**
     * `trim()` for characters, not bytes — `“”` comes off a quote whole. Left out, the
     * characters are white space.
     */
    public function trim(?string $characters = null): self
    {
        return $this->map(static fn (string $text): string => mb_trim($text, $characters));
    }

    /** `rtrim()`: the usual one — the full stop an editor typed at the end of a heading. */
    public function trimEnd(?string $characters = null): self
    {
        return $this->map(static fn (string $text): string => mb_rtrim($text, $characters));
    }

    public function trimStart(?string $characters = null): self
    {
        return $this->map(static fn (string $text): string => mb_ltrim($text, $characters));
    }

    public function stripPrefix(string $prefix): self
    {
        return $this->map(static fn (string $text): string => $prefix !== '' && str_starts_with($text, $prefix) ? substr($text, strlen($prefix)) : $text);
    }

    public function stripSuffix(string $suffix): self
    {
        return $this->map(static fn (string $text): string => $suffix !== '' && str_ends_with($text, $suffix) ? substr($text, 0, -strlen($suffix)) : $text);
    }

    /** Nothing typed but space: what `@if (trim($heading) !== '')` meant to ask. */
    public function isEmpty(): bool
    {
        return mb_trim($this->text) === '';
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
