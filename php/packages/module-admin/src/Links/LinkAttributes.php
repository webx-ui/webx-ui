<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

use Illuminate\Support\HtmlString;
use WebxUi\Admin\Screens\Types\LinkType;

/**
 * The attributes of an `<a>` for a link field, written once rather than in every block.
 *
 * A template is handed a `wx-link` value already resolved ({@see LinkType::resolve()})
 * and has four things to get right with it: the address, `target="_blank"` with the `rel` that
 * protects the page behind a new tab, `download` with the name the file was uploaded under, and
 * nothing at all for a draft or a file that is gone. Written by hand in each block that is four
 * places for one of them to be forgotten.
 *
 * The second argument is the address a block kept before it had a link field — `button_url` of a
 * hero saved last year. A link that was chosen wins; only an untouched link field falls back to the
 * old address, so a block whose type gained the new field prints exactly what it printed before.
 *
 * Printed with `{{ }}`: what comes back is `Htmlable`, every value in it already escaped.
 *
 *     <a class="b-hero__button" {{ $first }}>{{ $button_label }}</a>
 */
final class LinkAttributes
{
    /**
     * Null when there is nowhere to go — the template leaves the button out.
     */
    public static function of(mixed $link, mixed $fallback = null): ?HtmlString
    {
        if (is_array($link) && $link !== []) {
            return self::fromLink($link);
        }

        // The old field printed whatever it held, `viber:` and `whatsapp:` included, and still does:
        // a button that worked yesterday must not vanish. Only a scheme that runs script is dropped.
        if (is_string($fallback) && trim($fallback) !== '' && preg_match('#^\s*(javascript|data|vbscript):#i', $fallback) !== 1) {
            return self::html(['href' => trim($fallback)]);
        }

        return null;
    }

    /**
     * @param  array<mixed>  $link
     */
    private static function fromLink(array $link): ?HtmlString
    {
        $href = $link['url'] ?? null;

        if (! is_string($href) || $href === '' || ($link['available'] ?? true) === false) {
            return null;
        }

        $attributes = ['href' => $href];

        if (($link['new_tab'] ?? false) === true) {
            $attributes['target'] = '_blank';
        }

        if (is_string($link['rel'] ?? null) && $link['rel'] !== '') {
            $attributes['rel'] = $link['rel'];
        }

        if (is_string($link['download'] ?? null) && $link['download'] !== '') {
            $attributes['download'] = $link['download'];
        }

        return self::html($attributes);
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private static function html(array $attributes): HtmlString
    {
        $pairs = [];

        foreach ($attributes as $name => $value) {
            $pairs[] = $name.'="'.e($value).'"';
        }

        return new HtmlString(implode(' ', $pairs));
    }
}
