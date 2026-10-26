<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Rendering;

use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The widget's script, at most once per page (§12.4): a page with two configurators loads it once,
 * and both mount from it. `scoped`, so the flag belongs to the response being built and not to the
 * worker building it.
 *
 * Served from the package rather than published into the site's build — as `inbox.js` is — so a
 * configurator does not stop counting after a release nobody rebuilt the site for. `dist/` is
 * committed; the test holds it to its sources and to its weight.
 */
final class Assets
{
    public const string SCRIPT = __DIR__.'/../../dist/configurator.js';

    private bool $printed = false;

    private static ?string $version = null;

    public function __construct(private readonly UrlGenerator $url) {}

    /** The address of the script the first time a configurator on this page asks; null after. */
    public function script(): ?string
    {
        if ($this->printed) {
            return null;
        }

        $this->printed = true;

        return $this->url->route('webx.configurator.script', ['v' => self::version()]);
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
