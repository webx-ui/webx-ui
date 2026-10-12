<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Composer\InstalledVersions;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The base look of the installed modules (spec §10.2): each `webx-ui/module-*` with public pages
 * carries `resources/theme/*.css` on site tokens and its own classes, and this puts all of it on
 * the page, below every theme layer (§7.1) — a module looks decent under any theme, and any theme
 * restyles it by writing the same class later in the cascade.
 *
 * One file for all of them, `public/themes/modules/<hash>/modules.css`, written by
 * `webx:theme:sync` and linked by `@webxTheme`: a handful of small stylesheets is one request
 * rather than ten, and the hash of what they say together names the directory, so the link is
 * cached forever like a packaged layer's. A module that is removed by Composer drops out of the
 * file on the next sync. `modules` has no slash, so no package name — `vendor/name` — is it.
 */
class ModuleStyles
{
    public const string NAME = 'modules';

    public const string FILE = 'modules.css';

    /**
     * @param  list<string>|null  $modules  Directories of the modules to read; null asks Composer.
     */
    public function __construct(
        private readonly ThemeAssets $assets,
        private readonly ?array $modules = null,
    ) {}

    /**
     * Every stylesheet, keyed by `<module>/<file>`, in that order — the order does not depend on
     * the order Composer or the disk lists them in.
     *
     * @return array<string, string>
     */
    public function sources(): array
    {
        $sources = [];

        foreach ($this->modules() as $module) {
            $module = rtrim(str_replace('\\', '/', $module), '/');

            foreach (glob($module.'/resources/theme/*.css') ?: [] as $file) {
                $sources[basename($module).'/'.basename($file)] = str_replace('\\', '/', $file);
            }
        }

        ksort($sources, SORT_STRING);

        return $sources;
    }

    /**
     * The directories of the modules this reads.
     *
     * @return list<string>
     */
    public function modules(): array
    {
        return $this->modules ?? self::installed();
    }

    /** The file as it is published: each stylesheet under a comment naming where it came from. */
    public function css(): string
    {
        $parts = [];

        foreach ($this->sources() as $name => $file) {
            $parts[] = "/* {$name} */\n".rtrim((string) file_get_contents($file))."\n";
        }

        return implode("\n", $parts);
    }

    /** What sync has to publish: the hash of the file, or null when no module carries a stylesheet. */
    public function hash(): ?string
    {
        $css = $this->css();

        return $css === '' ? null : substr(sha1($css), 0, 12);
    }

    /** The hash pages link to now, or null before the first sync. */
    public function current(): ?string
    {
        return $this->assets->current($this->layer());
    }

    /** @return 'published'|'unchanged'|'nothing' */
    public function publish(bool $force = false): string
    {
        $css = $this->css();

        if ($css === '') {
            return 'nothing';
        }

        return $this->assets->place($this->layer(), substr(sha1($css), 0, 12), static function (string $staging) use ($css): void {
            file_put_contents($staging.'/'.self::FILE, $css);
        }, $force);
    }

    public function url(UrlGenerator $urls): ?string
    {
        return $this->assets->url($this->layer(), self::FILE, $urls);
    }

    /**
     * The directories of the installed `webx-ui/module-*` packages.
     *
     * @return list<string>
     */
    public static function installed(): array
    {
        $paths = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            if (str_starts_with($package, 'webx-ui/module-')) {
                $path = InstalledVersions::getInstallPath($package);

                if ($path !== null) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /** Where the file goes: the same scheme as a packaged layer, under `public/themes/modules`. */
    private function layer(): ThemeManifest
    {
        return new ThemeManifest(self::NAME, '', false);
    }
}
