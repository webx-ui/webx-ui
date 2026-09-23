<?php

declare(strict_types=1);

namespace WebxUi\Admin\Panel;

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

/**
 * What is installed on the server, as far as the panel's front end is concerned.
 *
 * Composer already keeps this list, `extra` and all, in `vendor/composer/installed.json`, so
 * there is nothing to maintain: install a package and it appears here; remove it and it does
 * not. The file is found through the autoloader rather than through `base_path()`, because
 * under Testbench the application root and the vendor directory are not in the same tree.
 */
final class PackageRegistry
{
    public function __construct(
        private readonly Filesystem $files,
        private ?string $installed = null,
    ) {}

    /**
     * Read the installed packages that say something about the panel, in a settled order.
     *
     * @return list<PanelPackage>
     */
    public function packages(): array
    {
        $path = $this->installed ??= $this->locate();

        if ($path === null || ! $this->files->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $this->files->get($path), true);

        // Composer 2 wraps the list; Composer 1 wrote a bare array. Both still turn up.
        $installed = is_array($decoded) ? ($decoded['packages'] ?? $decoded) : [];

        $packages = [];

        foreach (is_array($installed) ? $installed : [] as $package) {
            if (! is_array($package) || ! is_string($package['name'] ?? null)) {
                continue;
            }

            $extra = $package['extra'] ?? null;

            $read = is_array($extra)
                ? PanelPackage::fromExtra($package['name'], $extra)
                : null;

            if ($read !== null) {
                $packages[$package['name']] = $read;
            }
        }

        // Composer writes them in whatever order it resolved them, and an entry file that
        // changes shape between two identical runs is one nobody can review.
        ksort($packages);

        return array_values($packages);
    }

    /**
     * Every npm package the installed Composer packages ask the site to have.
     *
     * @return array<string, string>
     */
    public function npm(): array
    {
        $npm = [];

        foreach ($this->packages() as $package) {
            $npm = [...$npm, ...$package->npm];
        }

        ksort($npm);

        return $npm;
    }

    private function locate(): ?string
    {
        // vendor/composer/ClassLoader.php sits beside installed.json, wherever vendor is.
        $file = (new ReflectionClass(ClassLoader::class))->getFileName();

        return $file === false ? null : dirname($file).'/installed.json';
    }
}
