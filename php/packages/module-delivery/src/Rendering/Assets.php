<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Rendering;

use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The delivery step's script, at most once per page (§12.3): the page loads it once, whatever
 * asks for it. `scoped`, so the flag belongs to the response being built
 * and not to the worker building it.
 *
 * Served from the package, as the cart's is: a site installs it from Composer with no Node
 * to build it with, so `dist/` is committed and the test holds it to its sources and its weight.
 */
final class Assets
{
    public const string SCRIPT = __DIR__.'/../../dist/delivery.js';

    private bool $printed = false;

    private static ?string $version = null;

    public function __construct(private readonly UrlGenerator $url) {}

    /** The address of the script the first time something on this page asks; null after. */
    public function script(): ?string
    {
        if ($this->printed) {
            return null;
        }

        $this->printed = true;

        return $this->url->route('webx-delivery.script', ['v' => self::version()]);
    }

    /** What is served, read once per process — it cannot change under a running worker. */
    public static function contents(): string
    {
        return (string) @file_get_contents(self::SCRIPT);
    }

    public static function version(): string
    {
        return self::$version ??= substr(hash('xxh128', self::contents()), 0, 16);
    }
}
