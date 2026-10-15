<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use RuntimeException;
use stdClass;
use Symfony\Component\Finder\SplFileInfo;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeHead;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeLook;
use WebxUi\Themes\ThemeManifest;

/**
 * A packaged theme becomes the site's own (spec §14.3): its files are copied into the local
 * theme, and from then on its updates no longer reach the site. A deliberate step for a site
 * that has come to differ from the theme more than it shares with it.
 *
 * Two shapes. A site with a local theme over the package: the package is folded into `theme/`
 * under it — a file the site already has wins, as it won in the chain; tokens.json, the words of
 * the layout and the manifest are merged key by key the same way; the package's `src/` goes
 * under `src/<dir>/<package>/` and is imported first, where its stylesheet used to come. A site
 * standing on the package itself: the package becomes `theme/` whole.
 *
 * `webx-ui/theme-default` is never ejected: it is the value of every token and the shell every
 * site stands on, and the local theme overrides what it needs of it file by file.
 *
 * The look of the chain is compared before and after, and any difference is named. Composer and
 * npm are not run: the command says which to run.
 */
class EjectCommand extends Command
{
    protected $signature = 'webx:theme:eject
        {name? : The packaged theme to take into the site; the one the local theme stands on by default}
        {--path=theme : The local theme\'s directory, relative to the project root, for a site that has none yet}';

    protected $description = 'Copy a packaged theme into the site\'s local theme; its updates no longer reach the site';

    /** Never ejected: the bottom of every site's chain. */
    public const string DEFAULT = 'webx-ui/theme-default';

    /** What a package has and a local theme does not, or what is merged rather than copied. */
    private const array NOT_COPIED = [
        'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'phpunit.xml',
        'phpunit.xml.dist', 'README.md', 'CHANGELOG.md', 'LICENSE', 'LICENSE.md', '.gitignore', '.gitattributes',
        'AGENTS.md', 'tokens.json', 'theme.json',
    ];

    private const array NOT_COPIED_DIRECTORIES = ['dist', 'tests', 'vendor', 'node_modules', '.github', '.git', 'src'];

    public function handle(Filesystem $files, ThemeChain $chain, ThemeLocator $locator): int
    {
        $top = $chain->top();

        if ($top === null) {
            $this->components->error('The site has no theme (WEBX_THEME is empty): there is nothing to eject.');

            return self::FAILURE;
        }

        $package = $this->package($chain);

        if ($package === null) {
            return self::FAILURE;
        }

        $before = ThemeLook::of($chain);
        $short = substr($package->name, (int) strpos($package->name, '/') + 1);

        try {
            $local = $top->local ? $this->fold($files, $top, $package, $short) : $this->adopt($files, $package);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($local === null) {
            return self::FAILURE;
        }

        $this->components->info("{$package->name} is now part of {$local}/; its updates no longer reach this site.");
        $this->advise($files, $package, $local, ! $top->local);

        return $this->compare($locator, $local, $before) ? self::SUCCESS : self::FAILURE;
    }

    /** The layer to eject: named, or the only packaged theme the local one (or the site) stands on. */
    private function package(ThemeChain $chain): ?ThemeManifest
    {
        $top = $chain->top();
        assert($top !== null);

        $name = trim((string) $this->argument('name'));
        $layers = [];

        foreach ($chain->layers as $layer) {
            $layers[$layer->name] = $layer;
        }

        // Right below the local theme, or the top itself: a layer deeper down would leave the one
        // between standing on a theme that is no longer there.
        $candidates = $top->local ? $top->uses : [$top->name];

        if ($name === '') {
            if (count($candidates) !== 1) {
                $this->components->error($candidates === []
                    ? "{$top->name} stands on no packaged theme: there is nothing to eject."
                    : "{$top->name} stands on ".implode(', ', $candidates).': name the one to eject.');

                return null;
            }

            $name = $candidates[0];
        }

        if ($name === self::DEFAULT) {
            $this->components->error(self::DEFAULT.' is not ejected: it gives every token its value and every site its shell. Override what you need of it in the local theme, file by file.');

            return null;
        }

        if (! isset($layers[$name]) || $layers[$name]->local) {
            $this->components->error("{$name} is not a packaged theme of this site's chain.");

            return null;
        }

        if (! in_array($name, $candidates, true)) {
            $this->components->error("{$name} is not right below the local theme: eject ".implode(', ', $candidates).' first.');

            return null;
        }

        return $layers[$name];
    }

    /**
     * The package folded into the local theme under it. Returns the local theme's directory
     * relative to the project.
     */
    private function fold(Filesystem $files, ThemeManifest $local, ThemeManifest $package, string $short): string
    {
        $kept = 0;
        $copied = 0;
        $merged = 0;

        foreach ($this->filesOf($files, $package->path) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $into = $local->path.'/'.$relative;

            if (preg_match('~^lang/[^/]+/[^/]+\.php$~', $relative) === 1 && $files->exists($into)) {
                $files->put($into, self::wordsFile(array_replace_recursive(self::words($file->getPathname()), self::words($into))));
                $merged++;
            } elseif ($files->exists($into)) {
                $kept++;
            } else {
                $files->ensureDirectoryExists(dirname($into));
                $files->copy($file->getPathname(), $into);
                $copied++;
            }
        }

        $files->put($local->path.'/tokens.json', self::tokensFile(self::mergeTokens(self::tokens($package->path), self::tokens($local->path))));
        $this->foldSources($files, $local, $package, $short);
        $this->foldManifest($files, $local, $package);

        $this->components->twoColumnDetail('files', "{$copied} copied, {$kept} the local theme already had (kept), {$merged} files of words merged");
        $this->components->twoColumnDetail('tokens.json', 'merged — the local value wins where both set one');
        $this->components->twoColumnDetail('theme.json', 'uses '.(self::readManifest($local->path.'/theme.json')['uses'] === [] ? 'nothing' : implode(', ', self::readManifest($local->path.'/theme.json')['uses'])));

        return $this->relative($local->path);
    }

    /**
     * The package's `src/` under the local one's, each directory in a folder of the package's
     * name, and the local entries importing it first — where its stylesheet stood in the cascade.
     * A package published without `src/` gives its built `dist/theme.css` instead.
     */
    private function foldSources(Filesystem $files, ThemeManifest $local, ThemeManifest $package, string $short): void
    {
        $sources = $package->path.'/src';

        if (! $files->isDirectory($sources) && $files->exists($package->path.'/dist/theme.css')) {
            $files->ensureDirectoryExists($local->path.'/src/css/'.$short);
            $files->copy($package->path.'/dist/theme.css', $local->path.'/src/css/'.$short.'/theme.css');
        } elseif ($files->isDirectory($sources)) {
            foreach ($files->directories($sources) as $directory) {
                $files->copyDirectory($directory, $local->path.'/src/'.basename($directory).'/'.$short);
            }
        }

        foreach (['css' => "@import './{$short}/theme.css'", 'js' => "import './{$short}/theme.js'"] as $kind => $import) {
            if (! $files->exists("{$local->path}/src/{$kind}/{$short}/theme.{$kind}")) {
                continue;
            }

            $entry = "{$local->path}/src/{$kind}/theme.{$kind}";
            $contents = $files->exists($entry) ? (string) $files->get($entry) : '';

            if (str_contains($contents, $import)) {
                continue;
            }

            $files->ensureDirectoryExists(dirname($entry));
            $files->put($entry, self::prepend($contents, $import.';'));
            $this->components->twoColumnDetail("src/{$kind}/theme.{$kind}", "imports {$short}/theme.{$kind} first");
        }
    }

    /** The local manifest standing where the package stood: its `uses`, and every key it did not set. */
    private function foldManifest(Filesystem $files, ThemeManifest $local, ThemeManifest $package): void
    {
        $file = $local->path.'/theme.json';
        $raw = json_decode((string) $files->get($file), true);
        $theme = is_array($raw) && is_array($raw['theme'] ?? null) ? $raw['theme'] : [];
        $theirs = self::packageManifest($package->path);

        $uses = [];

        foreach ((array) ($theme['uses'] ?? []) as $used) {
            array_push($uses, ...($used === $package->name ? (array) ($theirs['uses'] ?? []) : [$used]));
        }

        $faces = (array) ($theme['fonts'] ?? []);
        $families = array_column($faces, 'family');

        foreach ((array) ($theirs['fonts'] ?? []) as $face) {
            if (is_array($face) && ! in_array($face['family'] ?? null, $families, true)) {
                $faces[] = $face;
            }
        }

        $merged = [...$theirs, ...$theme];
        $merged['uses'] = array_values(array_unique(array_filter($uses, 'is_string')));

        foreach (['requires', 'locales'] as $list) {
            $values = array_values(array_unique([...(array) ($theme[$list] ?? []), ...(array) ($theirs[$list] ?? [])]));

            if ($values !== []) {
                $merged[$list] = $values;
            }
        }

        if ($faces !== []) {
            $merged['fonts'] = array_values($faces);
        }

        // The settings tabs the package printed are still printed — by the views now copied here.
        $settings = (new ThemeChain([$local, $package]))->settings();

        if ($settings !== null) {
            $merged['settings'] = $settings === [] ? new stdClass : $settings;
        }

        $files->put($file, ThemePackage::json(['theme' => $merged]));
    }

    /**
     * The package becomes the site's local theme whole: a site that stood on it directly.
     * Returns the new directory relative to the project.
     */
    private function adopt(Filesystem $files, ThemeManifest $package): ?string
    {
        $relative = trim(str_replace('\\', '/', (string) $this->option('path')), '/');
        $target = $this->laravel->basePath($relative);

        if ($relative === '' || str_contains('/'.$relative.'/', '/../')) {
            $this->components->error("[{$relative}] is not a directory inside the project.");

            return null;
        }

        if ($files->isDirectory($target) && ($files->files($target, true) !== [] || $files->directories($target) !== [])) {
            $this->components->error("{$relative}/ is already there and not empty — nothing was copied.");

            return null;
        }

        foreach ($this->filesOf($files, $package->path) as $file) {
            $into = $target.'/'.str_replace('\\', '/', $file->getRelativePathname());
            $files->ensureDirectoryExists(dirname($into));
            $files->copy($file->getPathname(), $into);
        }

        if ($files->isDirectory($package->path.'/src')) {
            $files->copyDirectory($package->path.'/src', $target.'/src');
        } elseif ($files->exists($package->path.'/dist/theme.css')) {
            $files->ensureDirectoryExists($target.'/src/css');
            $files->copy($package->path.'/dist/theme.css', $target.'/src/css/theme.css');
        }

        if ($files->exists($package->path.'/tokens.json')) {
            $files->copy($package->path.'/tokens.json', $target.'/tokens.json');
        }

        $files->put($target.'/theme.json', ThemePackage::json(['theme' => self::packageManifest($package->path)]));

        $files->ensureDirectoryExists($target.'/tests');
        $files->copy(dirname(__DIR__, 2).'/stubs/local/tests/ThemeTest.php.stub', $target.'/tests/ThemeTest.php');

        $site = new SiteFiles($files, $this->laravel->basePath());
        $this->components->twoColumnDetail('.env', $site->setEnv('WEBX_THEME', $relative) ? "WEBX_THEME={$relative}" : "set WEBX_THEME={$relative}");
        $suite = $site->addTestSuite($relative);

        if ($suite !== null) {
            $this->components->twoColumnDetail('phpunit.xml', $suite);
        }

        foreach ($site->viteAdvice($relative, array_values(array_filter(ThemeHead::LOCAL_ENTRIES, fn (string $entry): bool => $files->exists($target.'/'.$entry))), true) as $advice) {
            $this->components->twoColumnDetail('vite.config.js', $advice);
        }

        return $relative;
    }

    private function advise(Filesystem $files, ThemeManifest $package, string $local, bool $wholePackage): void
    {
        $this->newLine();
        $this->line('  Next, in this order:');

        $steps = [];
        $composer = $this->laravel->basePath('composer.json');
        $required = $files->exists($composer) && str_contains((string) $files->get($composer), '"'.$package->name.'"');

        if ($required) {
            $steps[] = "composer remove {$package->name}   (nothing stands on it any more)";
        }

        $steps[] = 'npm run build   (the site\'s Vite builds '.$local.'/src now)';
        $steps[] = 'php artisan webx:theme:sync';
        $steps[] = 'php artisan webx:theme:check'.($wholePackage ? '' : '   (a language of the words now needs every key of en, the package\'s included)');

        foreach ($steps as $step => $command) {
            $this->line('  '.($step + 1).'. '.$command);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function compare(ThemeLocator $locator, string $local, array $before): bool
    {
        try {
            $after = ThemeLook::of(ThemeChain::resolve($local, $locator));
        } catch (ThemeException $e) {
            $this->components->error('The local theme does not read after the eject: '.$e->getMessage());

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

    /**
     * The package's files a local theme takes as they are: views, blocks, icons, assets, words,
     * demo — not its packaging, not `src/` (placed separately) and not tokens.json (merged).
     *
     * @return list<SplFileInfo>
     */
    private function filesOf(Filesystem $files, string $path): array
    {
        $taken = [];

        foreach ($files->allFiles($path, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $first = explode('/', $relative)[0];

            if ((str_contains($relative, '/') && in_array($first, self::NOT_COPIED_DIRECTORIES, true)) || (! str_contains($relative, '/') && in_array($relative, self::NOT_COPIED, true))) {
                continue;
            }

            $taken[] = $file;
        }

        return $taken;
    }

    private function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', $this->laravel->basePath()), '/').'/';

        return str_starts_with($path.'/', $base) ? rtrim(substr($path.'/', strlen($base)), '/') : $path;
    }

    /** @return array<string, mixed> */
    private static function packageManifest(string $path): array
    {
        $composer = json_decode((string) file_get_contents($path.'/composer.json'), true);
        $theme = is_array($composer) ? ($composer['extra']['webx']['theme'] ?? []) : [];

        return is_array($theme) ? $theme : [];
    }

    /** @return array{uses: list<string>} */
    private static function readManifest(string $file): array
    {
        $raw = json_decode((string) file_get_contents($file), true);

        return ['uses' => array_values(array_filter((array) ($raw['theme']['uses'] ?? []), 'is_string'))];
    }

    /** @return array<string, mixed> */
    private static function tokens(string $path): array
    {
        if (! is_file($path.'/tokens.json')) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path.'/tokens.json'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("{$path}/tokens.json: {$e->getMessage()}");
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Two tokens.json as the chain merged them: names and defaults by key, presets by name and then
     * by token, the higher one (`$local`) winning each.
     *
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $local
     * @return array<string, mixed>
     */
    private static function mergeTokens(array $package, array $local): array
    {
        $merged = [];

        foreach (['vocabulary', 'defaults'] as $key) {
            $values = [...(array) ($package[$key] ?? []), ...(array) ($local[$key] ?? [])];

            if ($values !== [] || $key === 'defaults') {
                $merged[$key] = $values;
            }
        }

        $presets = (array) ($package['presets'] ?? []);

        foreach ((array) ($local['presets'] ?? []) as $name => $preset) {
            $theirs = is_array($presets[$name] ?? null) ? $presets[$name] : [];
            $preset = is_array($preset) ? $preset : [];

            $presets[$name] = array_filter([
                'title' => $preset['title'] ?? $theirs['title'] ?? null,
                'tokens' => [...(array) ($theirs['tokens'] ?? []), ...(array) ($preset['tokens'] ?? [])],
            ], static fn ($value): bool => $value !== null);
        }

        if ($presets !== []) {
            $merged['presets'] = $presets;
        }

        return $merged;
    }

    /** @param  array<string, mixed>  $tokens */
    private static function tokensFile(array $tokens): string
    {
        // An empty object stays an object: "defaults": {} is what a fresh local theme has.
        $tokens = array_map(static fn ($value) => $value === [] ? new stdClass : $value, $tokens);

        return ThemePackage::json($tokens);
    }

    /** @return array<mixed> */
    private static function words(string $file): array
    {
        $lines = require $file;

        return is_array($lines) ? $lines : [];
    }

    /** @param  array<mixed>  $words */
    private static function wordsFile(array $words): string
    {
        return "<?php\n\n// Merged by webx:theme:eject: the package's words, the local theme's winning by key.\n\nreturn ".self::export($words, '').";\n";
    }

    /** A PHP array literal in the short syntax a lang file is written in; a list keeps no keys. */
    private static function export(mixed $value, string $indent): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $inner = $indent.'    ';
        $lines = [];

        foreach ($value as $key => $item) {
            $lines[] = $inner.(array_is_list($value) ? '' : var_export($key, true).' => ').self::export($item, $inner).',';
        }

        return "[\n".implode("\n", $lines)."\n{$indent}]";
    }

    /** An import above everything but a leading comment, where CSS requires an `@import` to be. */
    private static function prepend(string $contents, string $line): string
    {
        if (preg_match('~^\s*(/\*.*?\*/\s*|(?://[^\n]*\n)+\s*)~s', $contents, $match) === 1) {
            return $match[0].$line."\n".substr($contents, strlen($match[0]));
        }

        return $line."\n".$contents;
    }
}
