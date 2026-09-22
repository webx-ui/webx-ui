<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Admin\Panel\EntryFile;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Panel\PanelPackage;
use WebxUi\Auth\AuthServiceProvider;

/**
 * Wire the panel's front end into the application that hosts it.
 *
 * The panel is built by the site, not shipped prebuilt, because which modules it contains is a
 * decision only the site can make. Which modules those are, though, the server already knows:
 * every Composer package names its npm half under `extra.webx`. So this walks what is
 * installed and tops up the entry file, the dependencies, the Vite input and the config key —
 * or says plainly which one it could not.
 *
 * Run it again after installing a module and it adds four lines; run it twice in a row and the
 * second run changes nothing.
 */
final class PanelCommand extends Command
{
    protected $signature = 'webx:panel
                            {--sync : Wire in what is installed, leaving an existing entry file alone}
                            {--entry=resources/js/admin.ts : Where to write the entry file}
                            {--force : Overwrite the entry file if it already exists}';

    protected $description = 'Set up the admin panel front end in this application';

    public function handle(Filesystem $files, PackageRegistry $registry): int
    {
        $entry = trim((string) $this->option('entry'), '/');
        $path = $this->laravel->basePath($entry);
        $exists = $files->exists($path);

        if ($exists && ! $this->option('force') && ! $this->option('sync')) {
            $this->components->error(
                "{$entry} already exists. Pass --sync to wire in what is installed, or --force to start it over.",
            );

            return self::FAILURE;
        }

        if (! $exists || $this->option('force')) {
            $this->writeEntry($files, $path);
            $this->components->info("Entry written to {$entry}.");
        }

        $packages = $registry->packages();

        $this->syncEntry($files, $path, $entry, $packages);
        $this->syncDependencies($files, $registry->npm());
        $this->pointConfigAtEntry($files, $entry);
        $this->addViteInput($files, $entry);

        $this->newLine();
        $this->components->twoColumnDetail('Then', 'npm install && npm run build — or npm run dev while working');

        return self::SUCCESS;
    }

    private function writeEntry(Filesystem $files, string $path): void
    {
        // The stub follows what is actually installed: offering an auth plugin from a package
        // the application does not have would be a file that does not compile.
        $stub = class_exists(AuthServiceProvider::class) ? 'panel-auth' : 'panel';

        $files->ensureDirectoryExists(dirname($path));
        $files->put(
            $path,
            str_replace(
                '{{ path }}',
                '/'.ltrim((string) config('webx-admin.path'), '/'),
                $files->get(__DIR__."/../../stubs/{$stub}.stub"),
            ),
        );
    }

    /**
     * Top up the entry file from the registry, between its markers and nowhere else.
     *
     * @param  list<PanelPackage>  $packages
     */
    private function syncEntry(Filesystem $files, string $path, string $entry, array $packages): void
    {
        $file = new EntryFile((string) $files->get($path));
        $wanted = array_values(array_filter($packages, static fn (PanelPackage $p): bool => $p->wiresThePanel()));

        if (! $file->hasRegions()) {
            $this->reportMissingRegions($entry, $file, $wanted);

            return;
        }

        $added = [];

        foreach ($wanted as $package) {
            if ($this->isWired($file, $package)) {
                continue;
            }

            $specifier = $package->importSpecifier();

            if ($package->import !== null && $specifier !== null) {
                $file->addImport($package->import, $specifier, $package->importedNames());
            }

            if ($package->style !== null && ! $file->hasStyle($package->style)) {
                $file->addStyle($package->style);
            }

            foreach ($package->register as $call) {
                $file->addModule($call);
            }

            $added[] = $package->name;
        }

        if ($file->changed()) {
            $files->put($path, $file->contents());
        }

        $this->components->twoColumnDetail(
            $entry,
            $added === [] ? 'already wired' : 'wired in '.implode(', ', $added),
        );

        $this->warnAboutSignIn($entry, $file, $wanted);
    }

    /**
     * Whether the site already knows about this package.
     *
     * By the name of the call, not the whole line: `seo({ mediaField: WxMediaField })` is the
     * SEO module registered, and a site that took `connect()` back out meant it.
     */
    private function isWired(EntryFile $file, PanelPackage $package): bool
    {
        foreach ($package->registeredNames() as $name) {
            if ($file->registers($name)) {
                return true;
            }
        }

        if ($package->register !== []) {
            return false;
        }

        return ($package->style !== null && $file->hasStyle($package->style))
            || ($package->importSpecifier() !== null && $file->importsFrom((string) $package->importSpecifier()));
    }

    /**
     * The markers are gone, so say the lines instead of guessing where they went.
     *
     * Not a failure: the site owns this file, and erasing the markers is a legitimate way of
     * saying so. Everything else this command does still applies.
     *
     * @param  list<PanelPackage>  $packages
     */
    private function reportMissingRegions(string $entry, EntryFile $file, array $packages): void
    {
        $missing = array_values(array_filter($packages, fn (PanelPackage $p): bool => ! $this->isWired($file, $p)));

        if ($missing === []) {
            $this->components->twoColumnDetail($entry, 'no markers, and nothing missing');

            return;
        }

        $this->tell($entry, 'the webx: markers are gone — add these by hand');

        foreach ($missing as $package) {
            foreach ([$package->import, $package->style === null ? null : "import '{$package->style}'"] as $line) {
                if ($line !== null) {
                    $this->components->twoColumnDetail('', $line);
                }
            }

            foreach ($package->register as $call) {
                $this->components->twoColumnDetail('', "modules: [… {$call}]");
            }
        }
    }

    /**
     * Signing in is a plugin, and a plugin lives outside the markers.
     *
     * An entry file written before the auth package was installed has the sections it brings
     * but no way to reach them, and nothing about a panel that redirects to nowhere says why.
     *
     * @param  list<PanelPackage>  $packages
     */
    private function warnAboutSignIn(string $entry, EntryFile $file, array $packages): void
    {
        $auth = array_filter($packages, static fn (PanelPackage $p): bool => $p->name === 'webx-ui/module-auth');

        if ($auth === [] || preg_match('/\bauth\s*\(/', $file->contents()) === 1) {
            return;
        }

        $this->tell($entry, 'add `plugins: [auth()]` and `userMenu: WxUserMenu` — signing in is a plugin, not a section');
    }

    /**
     * Add the npm halves the installed packages ask for, without moving the ones already there.
     *
     * @param  array<string, string>  $npm
     */
    private function syncDependencies(Filesystem $files, array $npm): void
    {
        $path = $this->laravel->basePath('package.json');

        if ($npm === []) {
            return;
        }

        if (! $files->exists($path)) {
            $this->tell('package.json', 'not here — install '.implode(' ', array_keys($npm)));

            return;
        }

        $manifest = json_decode((string) $files->get($path), true);

        if (! is_array($manifest)) {
            $this->tell('package.json', 'could not be read — install '.implode(' ', array_keys($npm)));

            return;
        }

        $dependencies = is_array($manifest['dependencies'] ?? null) ? $manifest['dependencies'] : [];
        $added = [];

        foreach ($npm as $package => $range) {
            // Somewhere in the manifest is enough: a site that keeps these under devDependencies
            // has them installed, and moving somebody's dependency between sections is rude.
            foreach (['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies'] as $section) {
                if (is_array($manifest[$section] ?? null) && array_key_exists($package, $manifest[$section])) {
                    continue 2;
                }
            }

            $dependencies[$package] = $range;
            $added[] = $package;
        }

        if ($added === []) {
            $this->components->twoColumnDetail('package.json', 'already asks for every half');

            return;
        }

        ksort($dependencies);
        $manifest['dependencies'] = $dependencies;

        $files->put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->components->info('package.json now asks for '.implode(', ', $added).'.');
    }

    /**
     * Point the shell at the entry, publishing the configuration first if it is not there.
     */
    private function pointConfigAtEntry(Filesystem $files, string $entry): void
    {
        $config = $this->laravel->configPath('webx-admin.php');

        if (! $files->exists($config)) {
            $this->callSilently('vendor:publish', ['--tag' => 'webx-admin-config']);
        }

        if (! $files->exists($config)) {
            $this->tell('config/webx-admin.php', "'vite' => ['{$entry}'],");

            return;
        }

        // The value, not the file: the published configuration documents this very key in a
        // comment, and looking for the entry anywhere in the text finds the example.
        if (in_array($entry, (array) config('webx-admin.vite', []), true)) {
            $this->components->twoColumnDetail('config/webx-admin.php', 'already points here');

            return;
        }

        $contents = $files->get($config);

        $updated = str_replace("'vite' => [],", "'vite' => ['{$entry}'],", $contents);

        if ($updated === $contents) {
            $this->tell('config/webx-admin.php', "'vite' => ['{$entry}'],");

            return;
        }

        $files->put($config, $updated);
        $this->components->info("config/webx-admin.php now loads {$entry}.");
    }

    /**
     * Add the entry to the Laravel Vite plugin's inputs.
     *
     * Only when the shape is unambiguous. Rewriting somebody's build configuration on a guess
     * is worse than telling them one line to add.
     */
    private function addViteInput(Filesystem $files, string $entry): void
    {
        $path = collect(['vite.config.js', 'vite.config.ts', 'vite.config.mjs'])
            ->map(fn (string $name): string => $this->laravel->basePath($name))
            ->first(fn (string $candidate): bool => $files->exists($candidate));

        if ($path === null) {
            $this->tell('vite.config.js', "add '{$entry}' to the laravel() plugin's input");

            return;
        }

        $name = basename($path);
        $contents = $files->get($path);

        if (str_contains($contents, $entry)) {
            $this->components->twoColumnDetail($name, 'already builds it');

            return;
        }

        $updated = preg_replace(
            [
                // input: ['resources/js/app.js', …]
                '/(input:\s*\[)([^\]]*?)(\s*\])/s',
                // input: 'resources/js/app.js'
                "/input:\s*(['\"])((?:(?!\\1).)*)\\1/",
            ],
            [
                "$1$2,\n                '{$entry}',$3",
                "input: [$1$2$1, '{$entry}']",
            ],
            $contents,
            1,
            $count,
        );

        if ($count === 0 || $updated === null) {
            $this->tell($name, "add '{$entry}' to the laravel() plugin's input");

            return;
        }

        $files->put($path, $updated);
        $this->components->info("{$name} now builds {$entry}.");
    }

    private function tell(string $file, string $instruction): void
    {
        // Saying it beats guessing at somebody's build.
        $this->components->warn("{$file}: {$instruction}");
    }
}
