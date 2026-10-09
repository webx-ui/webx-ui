<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeHead;
use WebxUi\Themes\ThemeLocator;

/**
 * A new theme laid out by §4 of the spec, green from the first run.
 *
 * For now only the local kind — `theme/` inside a site, which `webx:setup` creates on every new
 * site over the theme it installed. It holds the manifest, an empty tokens.json, the two Vite
 * entries `@webxTheme` asks for and a test; everything else (views, blocks, icons) is added by
 * whoever needs it, because a directory that is not there is a layer that inherits.
 *
 * Writing `WEBX_THEME` and pointing the site's Vite at the entries are the site's business,
 * not this command's: `webx:setup` does both, and on any other site this says what to add.
 */
class MakeCommand extends Command
{
    protected $signature = 'webx:theme:make
        {name : What the theme is called; for a local theme also its directory}
        {--uses=* : The theme it stands on, by Composer name (webx-ui/theme-default)}
        {--local : A theme that lives inside this site rather than a package of its own}
        {--path= : Where to create it, relative to the project root; the name by default}';

    protected $description = 'Create a new theme over the one it stands on';

    public function handle(Filesystem $files, ThemeLocator $locator): int
    {
        if (! $this->option('local')) {
            $this->components->error('Only a local theme can be scaffolded so far: pass --local.');

            return self::FAILURE;
        }

        $name = trim((string) $this->argument('name'));
        $relative = trim(str_replace('\\', '/', (string) ($this->option('path') ?: $name)), '/');

        if ($relative === '' || str_contains('/'.$relative.'/', '/../')) {
            $this->components->error("[{$relative}] is not a directory inside the project.");

            return self::FAILURE;
        }

        $path = $this->laravel->basePath($relative);

        if ($files->isDirectory($path) && ($files->files($path) !== [] || $files->directories($path) !== [])) {
            $this->components->error("{$relative}/ is already there and not empty — nothing was written.");

            return self::FAILURE;
        }

        /** @var list<string> $uses */
        $uses = array_values(array_filter(array_map(trim(...), (array) $this->option('uses'))));

        // A typo here would only surface on the first page, as an exception about a theme
        // nobody can find; checked now, it is a message about the option that has the typo.
        foreach ($uses as $parent) {
            try {
                $locator->locate($parent, $relative);
            } catch (ThemeException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $this->write($files, $path, $name, $uses);
        $this->components->info("{$relative}/ created".($uses === [] ? '.' : ', standing on '.implode(', ', $uses).'.'));

        $this->addTestSuite($files, $relative);
        $this->adviseOnVite($files, $relative);

        if ((string) config('webx-themes.theme') !== $relative) {
            $this->components->twoColumnDetail('.env', "WEBX_THEME={$relative}");
        }

        return self::SUCCESS;
    }

    /** @param  list<string>  $uses */
    private function write(Filesystem $files, string $path, string $name, array $uses): void
    {
        $stubs = dirname(__DIR__, 2).'/stubs/local';

        $title = (string) config('app.name');

        $manifest = ['theme' => [
            'title' => $title !== '' && $title !== 'Laravel' ? $title : $name,
            'uses' => $uses,
        ]];

        $files->ensureDirectoryExists($path.'/src/css');
        $files->ensureDirectoryExists($path.'/src/js');
        $files->ensureDirectoryExists($path.'/tests');

        $files->put($path.'/theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        // Valid and empty: every value comes from below until the site names one of its own.
        $files->put($path.'/tokens.json', json_encode(['defaults' => new \stdClass], JSON_PRETTY_PRINT)."\n");

        foreach (ThemeHead::LOCAL_ENTRIES as $entry) {
            $files->copy($stubs.'/'.$entry, $path.'/'.$entry);
        }

        $files->copy($stubs.'/tests/ThemeTest.php.stub', $path.'/tests/ThemeTest.php');
    }

    /**
     * The theme's test runs with the site's own, so `php artisan test` covers it.
     *
     * A suite before `</testsuites>`, and only in a file that has that tag and no mention of
     * the directory: anything less ordinary is somebody's own arrangement, and they are told.
     */
    private function addTestSuite(Filesystem $files, string $relative): void
    {
        $phpunit = $this->laravel->basePath('phpunit.xml');

        if (! $files->exists($phpunit)) {
            return;
        }

        $contents = (string) $files->get($phpunit);

        if (str_contains($contents, "<directory>{$relative}/tests</directory>")) {
            return;
        }

        if (substr_count($contents, '</testsuites>') !== 1) {
            $this->components->twoColumnDetail('phpunit.xml', "add a test suite for {$relative}/tests");

            return;
        }

        $suite = '$1    <testsuite name="Theme">'."\n"
            .'$1        <directory>'.$relative.'/tests</directory>'."\n"
            .'$1    </testsuite>'."\n"
            .'$1</testsuites>';

        $files->put($phpunit, preg_replace('~^([ \t]*)</testsuites>~m', $suite, $contents, 1) ?? $contents);
        $this->components->twoColumnDetail('phpunit.xml', "runs {$relative}/tests");
    }

    private function adviseOnVite(Filesystem $files, string $relative): void
    {
        $config = $this->laravel->basePath('vite.config.js');
        $contents = $files->exists($config) ? (string) $files->get($config) : '';

        foreach (ThemeHead::LOCAL_ENTRIES as $entry) {
            if (! str_contains($contents, "{$relative}/{$entry}")) {
                $this->components->twoColumnDetail('vite.config.js', "add '{$relative}/{$entry}' to the laravel() plugin's input");
            }
        }
    }
}
