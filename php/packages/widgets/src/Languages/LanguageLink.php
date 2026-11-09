<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Languages;

/**
 * One language of the switcher: where the page is in it and what the language calls itself.
 *
 * `fallback` — the page has no translation there, and the link leads to that language's home
 * page instead (spec §13): a theme may show it differently, a reader is told so.
 *
 * `choice` — the link carries the reader's choice of language (`?webx_lang=<code>`, ROUTING §18)
 * and is drawn `rel="nofollow"`: a robot has the same page at the address `hreflang` gives it.
 */
final readonly class LanguageLink
{
    public function __construct(
        public string $code,
        public string $name,
        public string $url,
        public bool $current = false,
        public bool $fallback = false,
        public bool $choice = false,
    ) {}

    /** `pt_BR` as `hreflang` and `lang` want it: the same spelling module-seo prints in `<head>`. */
    public function tag(): string
    {
        return str_replace('_', '-', $this->code);
    }
}
