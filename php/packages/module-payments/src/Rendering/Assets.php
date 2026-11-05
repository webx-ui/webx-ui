<?php

declare(strict_types=1);

namespace WebxUi\Payments\Rendering;

use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The scripts of the payment pages, each at most once per page (spec §10.3, groups spec §6.5):
 * `step.js` writes the choice of the checkout's step, `methods.js` is the list of methods wherever
 * it stands. `scoped`, so the flags belong to the response being built and not to the worker
 * building it.
 *
 * Small enough to ship as written: no build, nothing to keep in step with a source.
 */
final class Assets
{
    public const string SCRIPT = __DIR__.'/../../resources/js/step.js';

    public const string METHODS = __DIR__.'/../../resources/js/methods.js';

    private bool $printed = false;

    private bool $methodsPrinted = false;

    /** @var array<string, string> */
    private static array $versions = [];

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

    /** The same for the list of methods. */
    public function methods(): ?string
    {
        if ($this->methodsPrinted) {
            return null;
        }

        $this->methodsPrinted = true;

        return $this->url->route('webx-payments.methods-script', ['v' => self::version(self::METHODS)]);
    }

    /** What is served, read once per process — it cannot change under a running worker. */
    public static function contents(string $file = self::SCRIPT): string
    {
        return (string) @file_get_contents($file);
    }

    public static function version(string $file = self::SCRIPT): string
    {
        return self::$versions[$file] ??= substr(hash('xxh128', self::contents($file)), 0, 16);
    }
}
