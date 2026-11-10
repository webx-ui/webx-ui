<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\After;

/**
 * Packages from an `installed.json` fixture, added to what `Composer\InstalledVersions` reports.
 *
 * For a test of code that asks Composer at runtime which packages are installed — the snapshot's
 * versions, the modules' styles, the debug bar's names — and has to see one that the test suite
 * itself does not install, such as a site module `site/module-offices`. The real packages stay
 * listed; the fixture's come on top, and Composer's own list is back after the test.
 */
trait InstalledPackages
{
    /** `install-path` is read like Composer's: relative to the directory of the file. */
    protected function installPackages(string $installedJson): void
    {
        $decoded = json_decode((string) file_get_contents($installedJson), true);
        $packages = is_array($decoded) ? ($decoded['packages'] ?? $decoded) : [];
        $versions = [];

        foreach (is_array($packages) ? $packages : [] as $package) {
            if (! is_array($package) || ! is_string($package['name'] ?? null)) {
                continue;
            }

            $version = is_string($package['version'] ?? null) ? $package['version'] : 'dev-main';
            $path = is_string($package['install-path'] ?? null) ? dirname($installedJson).'/'.$package['install-path'] : null;

            $versions[$package['name']] = [
                'pretty_version' => $version,
                'version' => $version,
                'reference' => null,
                'type' => is_string($package['type'] ?? null) ? $package['type'] : 'library',
                'aliases' => [],
                'dev_requirement' => false,
                ...($path !== null ? ['install_path' => realpath($path) ?: $path] : []),
            ];
        }

        InstalledVersions::reload([
            'root' => InstalledVersions::getRootPackage(),
            'versions' => $versions,
        ]);
    }

    #[After]
    protected function forgetInstalledPackages(): void
    {
        // Null makes Composer read its own `installed.php` again on the next question; its
        // runtime takes it, its docblock does not say so.
        InstalledVersions::reload(null); // @phpstan-ignore argument.type (null is Composer's own «not loaded yet»)
    }
}
