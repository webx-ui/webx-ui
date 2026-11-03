<?php

declare(strict_types=1);

namespace WebxUi\Payments\Rendering;

use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The payment step's script, at most once per page (spec §10.3). `scoped`, so the flag belongs to
 * the response being built and not to the worker building it.
 *
 * Small enough to ship as written: no build, nothing to keep in step with a source.
 */
final class Assets
{
    public const string SCRIPT = __DIR__.'/../../resources/js/step.js';

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

        return $this->url->route('webx-payments.script', ['v' => self::version()]);
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
