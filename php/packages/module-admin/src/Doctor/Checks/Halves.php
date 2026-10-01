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
     * Freshness is by content when the build left a record of it — `webx-sources.json` beside
     * the manifest, written by the plugin in the skeleton's `vite.config.js`: every file of the
     * site the build read, with its hash. Modification times lie in exactly the place this has
     * to be right: Docker reuses a cached build stage beside sources it copied a minute ago,
     * and the image comes out "stale" on the very build that made it. A site whose config
     * predates the plugin has no record, and gets the times — which do catch the commoner
     * failure, a deploy that installed a module, wired it in and never built.
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

        $assets = [];

        preg_match_all('/(?:src|href)="([^"]+)"/', $tags, $matches);

        foreach ($matches[1] as $url) {
            $asset = $this->app->publicPath(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));

            if ($this->files->exists($asset)) {
                $assets[] = $asset;
            }
        }

        $record = $this->sources($assets);

        return $record === null
            ? $this->byTime($entries, $assets)
            : $this->byContent($record);
    }

    /**
     * What the build says it read, or null when it left no record that can be trusted.
     *
     * Looked for upwards from the files the browser is served rather than at a fixed path: the
     * plugin writes it into Vite's output directory, which is the manifest's, and a site is free
     * to name that whatever it likes.
     *
     * @param  list<string>  $assets
     * @return array<string, string>|null
     */
    private function sources(array $assets): ?array
    {
        $public = rtrim(str_replace('\\', '/', $this->app->publicPath()), '/');

        foreach ($assets as $asset) {
            $directory = str_replace('\\', '/', dirname($asset));

            while (str_starts_with($directory, $public.'/')) {
                $path = $directory.'/webx-sources.json';

                if ($this->files->exists($path)) {
                    $record = json_decode((string) $this->files->get($path), true);

                    if (! is_array($record) || ($record['algorithm'] ?? null) !== 'sha256' || ! is_array($record['files'] ?? null)) {
                        return null;
                    }

                    return array_filter($record['files'], is_string(...));
                }

                $directory = dirname($directory);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $record
     * @return list<Diagnosis>
     */
    private function byContent(array $record): array
    {
        foreach ($record as $name => $hash) {
            $path = $this->app->basePath((string) $name);

            if (! $this->files->exists($path)) {
                return [Diagnosis::fail('Bundle', "{$name} is gone since the bundle was built from it — run `npm run build`.")];
            }

            if (! hash_equals($hash, hash_file('sha256', $path) ?: '')) {
                return [Diagnosis::fail('Bundle', "{$name} has changed since the bundle was built — run `npm run build`.")];
            }
        }

        return [Diagnosis::ok('Bundle', 'built from what is on disk now — '.count($record).' files, every one unchanged.')];
    }

    /**
     * @param  list<string>  $entries
     * @param  list<string>  $assets
     * @return list<Diagnosis>
     */
    private function byTime(array $entries, array $assets): array
    {
        $built = 0;

        foreach ($assets as $asset) {
            $built = max($built, (int) $this->files->lastModified($asset));
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
