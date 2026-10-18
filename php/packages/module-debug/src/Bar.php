<?php

declare(strict_types=1);

namespace WebxUi\Debug;

use WebxUi\Admin\SiteBar\Loader;
use WebxUi\Admin\Support\Tokens;

/**
 * The one script the debug bar puts on a page (`resources/js/debug-bar.js`), as served.
 *
 * Served by PHP under its content hash, like the panel on the site's loader: the site's build
 * knows nothing about it, and an address that changes with the file can be cached for a year.
 * The design tokens go in with it, rewritten from `:root` to `:host` by the same rule, so the bar
 * wears the panel's colours inside its shadow root and leaves the site's `--wx-*` alone.
 */
final class Bar
{
    private const PLACEHOLDER = "'' // tokens: written in when served";

    private ?string $body = null;

    public function body(): string
    {
        if ($this->body !== null) {
            return $this->body;
        }

        $source = (string) file_get_contents(self::path());
        $tokens = json_encode(Loader::hostTokens(Tokens::css()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->body = str_replace(self::PLACEHOLDER, $tokens, $source);
    }

    /** Twelve characters of the content hash: enough to tell two versions apart, short in a URL. */
    public function hash(): string
    {
        return substr(hash('sha256', $this->body()), 0, 12);
    }

    public static function path(): string
    {
        return dirname(__DIR__).'/resources/js/debug-bar.js';
    }
}
