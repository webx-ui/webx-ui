<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeHead;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeLook;

/**
 * The site's local theme becomes a package (spec §14.3): a look that worked out on one site, made
 * into a theme a second site can stand on — the road to the catalogue of themes (§21).
 *
 * `theme/` moves into the package's directory whole, `theme.json` becomes the `extra.webx.theme`
 * of a composer.json, and the package gets what a packaged theme has and a local one does not:
 * the build of a committed `dist/`, a test on the contract, a CI. The site then stands on the
 * package — a path repository and a requirement in its composer.json, `WEBX_THEME` in `.env` —
 * and looks the same: the look of the chain is compared before and after, and any difference is
 * named.
 *
 * Composer and npm are not run: the command says which to run (`composer update`, the package's
 * `npm run build`, `webx:theme:sync`). Until they are, the site has no theme stylesheet.
 */
class ExtractCommand extends Command
{
    protected $signature = 'webx:theme:extract
        {name : The package the local theme becomes, by Composer name (acme/theme-clinic)}
        {--path= : Where the package goes, relative to the project root; packages/<name> by default}';

    protected $description = 'Turn the site\'s local theme into a packaged theme the site then stands on';

    /** The local theme's own test is the site's; the package gets one on Testbench instead. */
    private const string LOCAL_TEST = 'tests/ThemeTest.php';

    public function handle(Filesystem $files, ThemeChain $chain, ThemeLocator $locator): int
    {
        $name = trim((string) $this->argument('name'));

        if (preg_match(ThemePackage::NAME, $name) !== 1) {
            $this->components->error("[{$name}] is not a Composer name: vendor/name (acme/theme-clinic).");

            return self::FAILURE;
        }

        $top = $chain->top();

        if ($top === null) {
            $this->components->error('The site has no theme (WEBX_THEME is empty): there is nothing to extract.');

            return self::FAILURE;
        }

        if (! $top->local) {
            $this->components->error("The site already stands on a packaged theme, {$top->name}: only a local theme becomes a package.");

            return self::FAILURE;
        }

        $base = rtrim(str_replace('\\', '/', $this->laravel->basePath()), '/');
        $source = $top->path;

        if (! str_starts_with($source.'/', $base.'/')) {
            $this->components->error("{$source} is not inside the project: extract a theme of this site.");

            return self::FAILURE;
        }

        $from = substr($source, strlen($base) + 1);
        $to = trim(str_replace('\\', '/', (string) ($this->option('path') ?: 'packages/'.substr($name, (int) strpos($name, '/') + 1))), '/');
        $target = $base.'/'.$to;

        if ($to === '' || str_contains('/'.$to.'/', '/../') || str_starts_with($to.'/', $from.'/')) {
            $this->components->error("[{$to}] is not a directory of the project outside {$from}/.");

            return self::FAILURE;
        }

        if ($files->isDirectory($target) && ($files->files($target, true) !== [] || $files->directories($target) !== [])) {
            $this->components->error("{$to}/ is already there and not empty — nothing was moved.");

            return self::FAILURE;
        }

        try {
            $manifest = json_decode((string) $files->get($source.'/theme.json'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->components->error("{$from}/theme.json: {$e->getMessage()}");

            return self::FAILURE;
        }

        $theme = is_array($manifest) && is_array($manifest['theme'] ?? null) ? $manifest['theme'] : [];
        $before = ThemeLook::of($chain);

        $this->move($files, $source, $target);
        $files->delete($target.'/theme.json');
        $this->dropLocalOnly($files, $target);

        $written = (new ThemePackage($files))->write($target, $name, $theme);
        $this->components->info("{$from}/ is now {$to}/, the packaged theme {$name}.");
        $this->components->twoColumnDetail('added', implode(', ', $written));

        $this->wire($files, $name, $from, $to);

        return $this->compare($locator, $name, $target, $before) ? self::SUCCESS : self::FAILURE;
    }

    /** One rename when the filesystem allows it; a copy and a delete when it does not (another drive, a watcher holding a file). */
    private function move(Filesystem $files, string $source, string $target): void
    {
        $files->ensureDirectoryExists(dirname($target));

        if ($files->isDirectory($target)) {
            $files->deleteDirectory($target);
        }

        if (! @rename($source, $target)) {
            $files->copyDirectory($source, $target);
            $files->deleteDirectory($source);
        }
    }

    /**
     * What only a local theme has: the site's test (the package gets its own) and the stub of
     * `src/js/theme.js` that says nothing — built, it would be an empty script on every page.
     */
    private function dropLocalOnly(Filesystem $files, string $target): void
    {
        $test = $target.'/'.self::LOCAL_TEST;

        if ($files->exists($test) && str_contains((string) $files->get($test), 'namespace Tests\\Theme;')) {
            $files->delete($test);
        }

        foreach ($files->exists($target.'/tests') ? $files->allFiles($target.'/tests') : [] as $left) {
            $this->components->twoColumnDetail('tests/'.$left->getRelativePathname(), 'a test of the site, not of the package: move it to the package\'s namespace or back to the site');
        }

        $script = $target.'/src/js/theme.js';

        if ($files->exists($script) && trim((string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', (string) $files->get($script))) === '') {
            $files->delete($script);

            if ($files->isEmptyDirectory($target.'/src/js')) {
                $files->deleteDirectory($target.'/src/js');
            }
        }
    }

    /** The site stands on the package now; what cannot be written is said. */
    private function wire(Filesystem $files, string $name, string $from, string $to): void
    {
        $site = new SiteFiles($files, $this->laravel->basePath());

        $this->components->twoColumnDetail('composer.json', $site->requireLocalPackage($name, $to)
            ? "requires {$name} from the path repository {$to}"
            : "add a path repository {$to} and require \"{$name}\": \"@dev\"");

        if ((string) config('webx-themes.theme') === $from) {
            $this->components->twoColumnDetail('.env', $site->setEnv('WEBX_THEME', $name) ? "WEBX_THEME={$name}" : "set WEBX_THEME={$name}");
        }

        if ($site->removeTestSuite($from)) {
            $this->components->twoColumnDetail('phpunit.xml', "no longer runs {$from}/tests — the package runs its own");
        }

        foreach ($site->viteAdvice($from, ThemeHead::LOCAL_ENTRIES, false) as $advice) {
            $this->components->twoColumnDetail('vite.config.js', $advice);
        }

        $this->newLine();
        $this->line('  Next, in this order:');

        foreach ([
            "composer update {$name}",
            "cd {$to} && npm install && npm run build   (commit dist/: sites install the theme without Node)",
            'php artisan webx:theme:sync',
            "php artisan webx:theme:make theme --local --uses={$name}   (a new theme/ for this site's own differences, when it has any)",
        ] as $step => $command) {
            $this->line('  '.($step + 1).'. '.$command);
        }
    }

    /**
     * The look of the chain with the package in place of the local theme, against the look before.
     * The package is told to the locator by path: Composer installs it only on the next update.
     *
     * @param  array<string, mixed>  $before
     */
    private function compare(ThemeLocator $locator, string $name, string $target, array $before): bool
    {
        try {
            $locator->register($name, $target);
            $after = ThemeLook::of(ThemeChain::resolve($name, $locator));
        } catch (ThemeException $e) {
            $this->components->error('The package does not read as a theme: '.$e->getMessage());

            return false;
        }

        $differences = ThemeLook::differences($before, $after);

        if ($differences === []) {
            $this->components->info('The same look: '.ThemeLook::summary($after).'.');

            return true;
        }

        foreach ($differences as $line) {
            $this->line("  {$line}");
        }

        $this->components->error('The look changed on the way: '.count($differences).' '.(count($differences) === 1 ? 'difference' : 'differences').'.');

        return false;
    }
}
