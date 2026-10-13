<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteBar;

use WebxUi\Admin\Support\Tokens;

/**
 * The one script the panel on the site puts on a page (`resources/js/site-bar.js`), as served.
 *
 * Served by PHP under its content hash, like the blocks runtime: the site's build knows nothing
 * about it and must not have to, and an address that changes with the file can be cached for a
 * year.
 *
 * The design tokens go in with it. The script draws in a shadow root on somebody else's page,
 * where neither the panel's stylesheet nor its variables exist, and a request for them before
 * the tab can paint would be one more thing an administrator's page waits for that a visitor's
 * does not. Rewritten from `:root` to `:host`, so they stop at the shadow root and the site's
 * own `--wx-*` (a theme built on the same tokens) is neither read nor overwritten.
 */
final class Loader
{
    private const PLACEHOLDER = "'' // tokens: written in when served";

    private ?string $body = null;

    public function body(): string
    {
        if ($this->body !== null) {
            return $this->body;
        }

        $source = (string) file_get_contents(self::path());
        $tokens = json_encode(self::hostTokens(Tokens::css()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->body = str_replace(self::PLACEHOLDER, $tokens, $source);
    }

    /** Twelve characters of the content hash: enough to tell two versions apart, short in a URL. */
    public function hash(): string
    {
        return substr(hash('sha256', $this->body()), 0, 12);
    }

    public static function path(): string
    {
        return dirname(__DIR__, 2).'/resources/js/site-bar.js';
    }

    /** The token sheet with every selector of the document turned into one of the shadow host. */
    public static function hostTokens(string $css): string
    {
        return strtr($css, [
            ":root:not([data-theme='light'])" => ":host(:not([data-theme='light']))",
            ':root' => ':host',
            "\n[data-theme='dark'] {" => "\n:host([data-theme='dark']) {",
            "\n[data-theme='light'] {" => "\n:host([data-theme='light']) {",
        ]);
    }
}
