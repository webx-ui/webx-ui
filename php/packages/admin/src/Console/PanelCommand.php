<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Auth\AuthServiceProvider;

/**
 * Wire the panel's front end into the application that hosts it.
 *
 * The panel is built by the site, not shipped prebuilt, because which modules it contains is a
 * decision only the site can make. That leaves three small chores — an entry file, a Vite
 * input, a config key — and this does them, or says plainly which one it could not.
 */
final class PanelCommand extends Command
{
    protected $signature = 'webx:panel
                            {--entry=resources/js/admin.ts : Where to write the entry file}
                            {--force : Overwrite the entry file if it already exists}';

    protected $description = 'Set up the admin panel front end in this application';

    public function handle(Filesystem $files): int
    {
        $entry = trim((string) $this->option('entry'), '/');
        $path = $this->laravel->basePath($entry);

        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->error("{$entry} already exists. Pass --force to overwrite it.");

            return self::FAILURE;
        }

        $this->writeEntry($files, $path);
        $this->components->info("Entry written to {$entry}.");

        $this->pointConfigAtEntry($files, $entry);
        $this->addViteInput($files, $entry);
        $this->reportPackages();

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

    private function reportPackages(): void
    {
        $packages = ['@webx-ui/admin'];

        if (class_exists(AuthServiceProvider::class)) {
            $packages[] = '@webx-ui/module-auth';
        }

        $this->newLine();
        $this->components->twoColumnDetail('Install', implode(' ', $packages));
        $this->components->twoColumnDetail('Then', 'npm run build — or npm run dev while working');
    }

    private function tell(string $file, string $instruction): void
    {
        // Saying it beats guessing at somebody's build.
        $this->components->warn("{$file}: {$instruction}");
    }
}
