<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Vite;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Panel\EntryFile;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Panel\PanelPackage;

/**
 * A module of the panel is two packages, and this is where they are asked whether they agree.
 *
 * Three ways they come apart, all of them quiet. A Composer package installed and never wired
 * into the entry file is a section the server answers for and nobody can reach. A call left in
 * the entry file whose package is gone is a section the bundle draws and the server 404s. And
 * a bundle older than the entry file is yesterday's panel — the commonest of the three, and the
 * one that looks most like the change simply not working (CLAUDE.md §4).
 */
final class Halves implements Check
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly Repository $config,
        private readonly PackageRegistry $registry,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $entries = array_values(array_filter((array) $this->config->get('webx-admin.vite', []), is_string(...)));

        if ($entries === []) {
            return [Diagnosis::fail(
                'Both halves',
                'config/webx-admin.php names no entry file, so the panel loads no front end at all — run `php artisan webx:panel --sync`.',
            )];
        }

        $wiring = array_values(array_filter(
            $this->registry->packages(),
            static fn (PanelPackage $package): bool => $package->wiresThePanel(),
        ));

        $found = [];

        foreach ($entries as $entry) {
            $path = $this->app->basePath($entry);

            if (! $this->files->exists($path)) {
                $found[] = Diagnosis::fail(
                    'Both halves',
                    "{$entry} is named in config/webx-admin.php and is not there — run `php artisan webx:panel --sync`.",
                );

                continue;
            }

            $file = new EntryFile((string) $this->files->get($path));

            $found = [...$found, ...$this->compare($entry, $file, $wiring)];
        }

        return [...$found, ...$this->bundle($entries)];
    }

    /**
     * What is installed against what is registered, both ways round.
     *
     * @param  list<PanelPackage>  $wiring
     * @return list<Diagnosis>
     */
    private function compare(string $entry, EntryFile $file, array $wiring): array
    {
        $missing = [];
        $registered = [];

        foreach ($wiring as $package) {
            $names = $package->registeredNames();
            $registered = [...$registered, ...$names];

            $wired = $names === []
                ? $package->style === null || $file->hasStyle($package->style)
                : array_filter($names, $file->registers(...)) !== [];

            if (! $wired) {
                $missing[] = $package->name.' ('.implode(', ', array_map(
                    static fn (string $name): string => $name.'()',
                    $names,
                )).')';
            }
        }

        $found = [];

        if ($missing !== []) {
            $found[] = Diagnosis::fail(
                'Both halves',
                "installed on the server and not in {$entry}: ".implode(', ', $missing)
                .' — run `php artisan webx:panel --sync`, then `npm run build`.',
            );
        }

        // The other way round: a call the entry still makes for a package that has been
        // removed. The build fails outright when the npm half went with it, and draws a
        // section nothing answers for when it did not.
        $orphans = array_values(array_filter(
            $this->registeredIn($file),
            static fn (string $name): bool => ! in_array($name, $registered, true),
        ));

        if ($orphans !== []) {
            $found[] = Diagnosis::warn(
                'Both halves',
                "{$entry} registers ".implode(', ', array_map(static fn (string $n): string => $n.'()', $orphans))
                .', and no installed package claims that — take the line out, or install the package behind it.',
            );
        }

        if ($found === []) {
            $found[] = Diagnosis::ok(
                'Both halves',
                count($wiring).' packages on the server, all of them in '.$entry.'.',
            );
        }

        return $found;
    }

    /**
     * The calls the entry file makes, by name.
     *
     * Only inside the markers: a site is free to register something of its own further down,
     * and a module the site wrote itself is not a half of anything here.
     *
     * @return list<string>
     */
    private function registeredIn(EntryFile $file): array
    {
        $region = $file->region('modules') ?? '';

        preg_match_all('/(?:^|[\s,\[.])\.{0,3}([A-Za-z_$][\w$]*)\s*\(/m', $region, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Whether the built bundle exists and was built from what is on disk now.
     *
     * Asked of Vite rather than of a path, because Vite is what the shell view asks at request
     * time: the same call, the same manifest, the same exception. What comes back names the
     * files the browser is actually served, wherever the site put its build directory.
     *
     * Freshness is by modification time, which is all there is to go on and is enough — the
     * failure this catches is a deploy that installed a module, wired it in and never built,
     * and that leaves the entry file minutes newer than the bundle.
     *
     * @param  list<string>  $entries
     * @return list<Diagnosis>
     */
    private function bundle(array $entries): array
    {
        $vite = $this->app->make(Vite::class);

        if ($this->files->exists($vite->hotFile())) {
            return [Diagnosis::warn('Bundle', 'Vite is running in dev mode — `public/hot` is there, so nothing is served from the build.')];
        }

        try {
            $tags = (string) $vite($entries);
        } catch (Throwable $failure) {
            return [Diagnosis::fail(
                'Bundle',
                $failure->getMessage().' — run `npm install && npm run build`.',
            )];
        }

        $built = 0;

        preg_match_all('/(?:src|href)="([^"]+)"/', $tags, $matches);

        foreach ($matches[1] as $url) {
            $asset = $this->app->publicPath(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));

            if ($this->files->exists($asset)) {
                $built = max($built, (int) $this->files->lastModified($asset));
            }
        }

        $changed = 0;
        $newest = '';

        foreach ($entries as $entry) {
            $path = $this->app->basePath($entry);

            if ($this->files->exists($path) && $this->files->lastModified($path) > $changed) {
                $changed = (int) $this->files->lastModified($path);
                $newest = $entry;
            }
        }

        return $built >= $changed
            ? [Diagnosis::ok('Bundle', 'built, and no newer than every entry it was built from.')]
            : [Diagnosis::fail('Bundle', "{$newest} has changed since the bundle was built — run `npm run build`.")];
    }
}
