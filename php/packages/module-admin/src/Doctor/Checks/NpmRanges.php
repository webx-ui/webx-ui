<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\VersionRange;
use WebxUi\Admin\Panel\PackageRegistry;

/**
 * Whether the npm halves the site installs are new enough for the Composer halves it has.
 *
 * Every Composer package names the range it needs under `extra.webx.npm`, and the release
 * pipeline writes those ranges at the same moment as the versions themselves, so they cannot
 * drift. What can drift is the site: a `package.json` written six months ago asks for
 * `^0.19.0`, npm is free to leave 0.19.4 where it is, and the module installed today needs
 * 0.21. Nothing says so until the build stops on `[MISSING_EXPORT]` — or, worse, does not, and
 * a component is simply missing at runtime.
 *
 * Both sides are asked: what the site declares, and what is actually in `node_modules`. They
 * disagree more often than one would think, because `npm install <name>@<older>` rewrites the
 * manifest and a `file:` link does not.
 */
final class NpmRanges implements Check
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly PackageRegistry $registry,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $needed = $this->registry->npm();

        if ($needed === []) {
            return [];
        }

        $path = $this->app->basePath('package.json');

        if (! $this->files->exists($path)) {
            return [Diagnosis::fail(
                'npm halves',
                'there is no package.json — run `php artisan webx:panel --sync`, then `npm install`.',
            )];
        }

        $manifest = json_decode((string) $this->files->get($path), true);
        $declared = [];

        foreach (['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies'] as $section) {
            if (is_array($manifest) && is_array($manifest[$section] ?? null)) {
                $declared = [...$declared, ...$manifest[$section]];
            }
        }

        $found = [];

        foreach ($needed as $package => $range) {
            $found = [...$found, ...$this->one($package, $range, $declared)];
        }

        return $found === []
            ? [Diagnosis::ok('npm halves', count($needed).' packages, each installed at the version the server asks for.')]
            : $found;
    }

    /**
     * @param  array<string, mixed>  $declared
     * @return list<Diagnosis>
     */
    private function one(string $package, string $range, array $declared): array
    {
        $asked = $declared[$package] ?? null;

        if (! is_string($asked)) {
            return [Diagnosis::fail(
                'npm halves',
                "package.json does not ask for {$package}, which the server needs at {$range} — run `php artisan webx:panel --sync`, then `npm install`.",
            )];
        }

        if (VersionRange::below($asked, $range)) {
            return [Diagnosis::fail(
                'npm halves',
                "package.json asks for {$package}@{$asked}; the installed Composer half needs {$range} — run `npm install {$package}@{$range}`.",
            )];
        }

        $installed = $this->installed($package);

        if ($installed !== null && VersionRange::below($installed, $range)) {
            return [Diagnosis::fail(
                'npm halves',
                "node_modules has {$package}@{$installed}, below the {$range} the server needs — run `npm install {$package}@{$range}`.",
            )];
        }

        return [];
    }

    /**
     * The version actually unpacked into `node_modules`, or null when there is nothing to read.
     *
     * Missing is not a failure of its own: `npm install` has either not run here or does not
     * need to (a container that builds elsewhere), and the declared range above already says
     * whether the site is asking for the right thing.
     */
    private function installed(string $package): ?string
    {
        $path = $this->app->basePath('node_modules/'.$package.'/package.json');

        if (! $this->files->exists($path)) {
            return null;
        }

        $manifest = json_decode((string) $this->files->get($path), true);
        $version = is_array($manifest) ? ($manifest['version'] ?? null) : null;

        return is_string($version) ? $version : null;
    }
}
