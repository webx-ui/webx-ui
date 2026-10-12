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
 * Two kinds. A local theme (`--local`) is `theme/` inside a site, which `webx:setup` creates on
 * every new site over the theme it installed: the manifest, an empty tokens.json, the two Vite
 * entries `@webxTheme` asks for and a test. A packaged theme is a Composer package of its own —
 * a look several sites stand on: the manifest in its composer.json, an empty tokens.json and
 * `src/css`, the build of its committed `dist/` (already built, empty), a test on
 * `Testing\ThemeContract`, a CI. Everything else (views, blocks, icons) is added by whoever
 * needs it, because a directory that is not there is a layer that inherits.
 *
 * Wiring a site to the new theme is the site's business: `webx:setup` does it for the local
 * kind; for the rest this says what to add, and never runs Composer or npm.
 */
class MakeCommand extends Command
{
    protected $signature = 'webx:theme:make
        {name : A local theme\'s name, also its directory; a packaged theme\'s Composer name (vendor/name)}
        {--uses=* : The theme it stands on, by Composer name (webx-ui/theme-default, the default for a packaged theme)}
        {--local : A theme that lives inside this site rather than a package of its own}
        {--path= : Where to create it, relative to the project root; the name for a local theme, packages/<name> for a packaged one}';

    protected $description = 'Create a new theme over the one it stands on: a local theme/ of the site, or a packaged theme';

    public function handle(Filesystem $files, ThemeLocator $locator): int
    {
        $name = trim((string) $this->argument('name'));
        $local = (bool) $this->option('local');

        if (! $local && preg_match(ThemePackage::NAME, $name) !== 1) {
            $this->components->error("[{$name}] is not a Composer name: a packaged theme is vendor/name (acme/theme-clinic); a theme inside this site is --local.");

            return self::FAILURE;
        }

        $default = $local ? $name : 'packages/'.substr($name, (int) strpos($name, '/') + 1);
        $relative = trim(str_replace('\\', '/', (string) ($this->option('path') ?: $default)), '/');

        if ($relative === '' || str_contains('/'.$relative.'/', '/../')) {
            $this->components->error("[{$relative}] is not a directory inside the project.");

            return self::FAILURE;
        }

        $path = $this->laravel->basePath($relative);

        if ($files->isDirectory($path) && ($files->files($path, true) !== [] || $files->directories($path) !== [])) {
            $this->components->error("{$relative}/ is already there and not empty — nothing was written.");

            return self::FAILURE;
        }

        /** @var list<string> $uses */
        $uses = array_values(array_filter(array_map(trim(...), (array) $this->option('uses'))));

        // A packaged theme with nothing below owes a value to every token; a new one has none.
        if (! $local && $uses === []) {
            $uses = ['webx-ui/theme-default'];
        }

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

        return $local ? $this->local($files, $path, $relative, $name, $uses) : $this->packaged($files, $path, $relative, $name, $uses);
    }

    /** @param  list<string>  $uses */
    private function local(Filesystem $files, string $path, string $relative, string $name, array $uses): int
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

        $files->put($path.'/theme.json', ThemePackage::json($manifest));

        // Valid and empty: every value comes from below until the site names one of its own.
        $files->put($path.'/tokens.json', json_encode(['defaults' => new \stdClass], JSON_PRETTY_PRINT)."\n");

        foreach (ThemeHead::LOCAL_ENTRIES as $entry) {
            $files->copy($stubs.'/'.$entry, $path.'/'.$entry);
        }

        $files->copy($stubs.'/tests/ThemeTest.php.stub', $path.'/tests/ThemeTest.php');
        $this->components->info("{$relative}/ created".($uses === [] ? '.' : ', standing on '.implode(', ', $uses).'.'));

        $site = new SiteFiles($files, $this->laravel->basePath());
        $suite = $site->addTestSuite($relative);

        if ($suite !== null) {
            $this->components->twoColumnDetail('phpunit.xml', $suite);
        }

        foreach ($site->viteAdvice($relative, ThemeHead::LOCAL_ENTRIES, true) as $advice) {
            $this->components->twoColumnDetail('vite.config.js', $advice);
        }

        if ((string) config('webx-themes.theme') !== $relative) {
            $this->components->twoColumnDetail('.env', "WEBX_THEME={$relative}");
        }

        return self::SUCCESS;
    }

    /** @param  list<string>  $uses */
    private function packaged(Filesystem $files, string $path, string $relative, string $name, array $uses): int
    {
        $stubs = dirname(__DIR__, 2).'/stubs/package';
        $title = ucwords(str_replace(['-', '_', '.'], ' ', substr($name, (int) strpos($name, '/') + 1)));

        $files->ensureDirectoryExists($path.'/src/css');
        $files->copy($stubs.'/src/css/theme.css', $path.'/src/css/theme.css');
        $files->put($path.'/tokens.json', json_encode(['defaults' => new \stdClass], JSON_PRETTY_PRINT)."\n");

        $package = new ThemePackage($files);
        $package->write($path, $name, ['title' => $title, 'uses' => $uses]);
        $package->emptyDist($path);

        $this->components->info("{$relative}/ created: the packaged theme {$name}, standing on ".implode(', ', $uses).'.');

        foreach ([
            'test it' => "cd {$relative} && composer install && vendor/bin/phpunit",
            'build dist/' => "cd {$relative} && npm install && npm run build — after every change to src/, and commit dist/",
            'a site on it' => "\"uses\": [\"{$name}\"] in the site's theme/theme.json, the package required from Composer (a path repository while it lives here)",
        ] as $what => $how) {
            $this->components->twoColumnDetail($what, $how);
        }

        return self::SUCCESS;
    }
}
