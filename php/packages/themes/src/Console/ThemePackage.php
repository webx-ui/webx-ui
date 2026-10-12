<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use WebxUi\Themes\ThemeSources;

/**
 * The files that make a directory a packaged theme (spec §4, §17): the ones `webx:theme:make`
 * writes for a new theme and `webx:theme:extract` adds to a local one it turns into a package.
 *
 * Only what a package has and a local theme does not — the composer.json that carries the
 * manifest, the build of `dist/`, the test, the CI. The look itself (tokens.json, src/, views/…)
 * is the caller's: new and empty for `make`, the site's own for `extract`.
 */
final class ThemePackage
{
    /** What Composer accepts as a package name. */
    public const string NAME = '~^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$~';

    public function __construct(private readonly Filesystem $files) {}

    /**
     * Writes the package's own files into `$path`, never over one that is there: a theme being
     * extracted may already carry a README or a test of its own.
     *
     * @param  array<string, mixed>  $manifest  What goes under `extra.webx.theme` — the local theme.json's `theme`, or a new one.
     * @return list<string> The files written, relative to `$path`.
     */
    public function write(string $path, string $name, array $manifest): array
    {
        $stubs = dirname(__DIR__, 2).'/stubs/package';
        $title = is_string($manifest['title'] ?? null) && $manifest['title'] !== '' ? $manifest['title'] : $name;
        $uses = array_values(array_filter((array) ($manifest['uses'] ?? []), 'is_string'));
        $written = [];

        $put = function (string $file, string $contents) use ($path, &$written): void {
            if ($this->files->exists($path.'/'.$file)) {
                return;
            }

            $this->files->ensureDirectoryExists(dirname($path.'/'.$file));
            $this->files->put($path.'/'.$file, $contents);
            $written[] = $file;
        };

        $put('composer.json', self::json($this->composer($name, $title, $uses, $manifest)));
        $put('package.json', self::json([
            'name' => str_replace('/', '-', $name).'-build',
            'private' => true,
            'type' => 'module',
            'description' => "Not published: the build of {$name}'s dist/, which is committed.",
            'scripts' => ['build' => 'vite build', 'watch' => 'vite build --watch'],
            'devDependencies' => ['vite' => '^7.1.14'],
        ], 2));
        $put('vite.config.js', (string) $this->files->get($stubs.'/vite.config.js'));
        $put('phpunit.xml.dist', (string) $this->files->get($stubs.'/phpunit.xml.dist'));
        $put('.github/workflows/theme.yml', (string) $this->files->get($stubs.'/.github/workflows/theme.yml'));
        $put('.gitignore', "/vendor/\n/node_modules/\ncomposer.lock\npackage-lock.json\n");
        $put('tests/ThemeTest.php', str_replace('{{ namespace }}', self::namespace($name), (string) $this->files->get($stubs.'/tests/ThemeTest.php.stub')));
        $put('README.md', "# {$name}\n\n{$title} — a theme for sites built on [WebX UI](https://github.com/webx-ui/webx-ui).\n\n"
            ."A site stands on it with `\"uses\": [\"{$name}\"]` in its `theme/theme.json`.\n\n"
            ."After a change to `src/`: `npm install && npm run build`, and commit `dist/` — sites install\n"
            ."this theme from Composer and have no Node to build it. `vendor/bin/phpunit` runs its contract.\n");

        return $written;
    }

    /**
     * An empty `dist/` that matches the stubs of `src/`, so the contract is green before the
     * first build; the build writes the same `sources.json` and an equally empty stylesheet.
     */
    public function emptyDist(string $path): void
    {
        $this->files->ensureDirectoryExists($path.'/dist');
        $this->files->put($path.'/dist/theme.css', '');
        $this->files->put($path.'/dist/sources.json', ThemeSources::manifest($path));
    }

    /** `acme/theme-clinic` → `Acme\ThemeClinic`, the namespace of the theme's tests. */
    public static function namespace(string $name): string
    {
        return implode('\\', array_map(static fn (string $part): string => Str::studly($part), explode('/', $name)));
    }

    /**
     * The constraint a theme puts on a package the site already has: the installed version's
     * minor, as `theme-default` does — every webx-ui package moves with one shared version.
     */
    public static function constraint(string $package): string
    {
        if (! InstalledVersions::isInstalled($package)) {
            return '*';
        }

        $version = ltrim((string) InstalledVersions::getPrettyVersion($package), 'v');

        return preg_match('/^(\d+)\.(\d+)\.\d+$/', $version, $match) === 1 ? "^{$match[1]}.{$match[2]}.0" : '*';
    }

    /** JSON as Composer and npm write it: unescaped, `$indent` spaces, a newline at the end. */
    public static function json(mixed $data, int $indent = 4): string
    {
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($indent !== 4) {
            $json = (string) preg_replace_callback('/^( +)/m', static fn (array $m): string => str_repeat(' ', intdiv(strlen($m[1]), 4) * $indent), $json);
        }

        return $json."\n";
    }

    /**
     * @param  list<string>  $uses
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function composer(string $name, string $title, array $uses, array $manifest): array
    {
        $require = ['php' => '^8.4', 'webx-ui/themes' => self::constraint('webx-ui/themes')];

        foreach ($uses as $used) {
            $require[$used] = self::constraint($used);
        }

        return [
            'name' => $name,
            'description' => "{$title} — a theme for sites built on WebX UI.",
            'type' => 'webx-theme',
            // Not open source until its owner says so: a theme is usually a client's look.
            'license' => 'proprietary',
            'require' => $require,
            'require-dev' => [
                'orchestra/testbench' => '^11.0',
                'phpunit/phpunit' => '^12.0 || ^13.0',
            ],
            'autoload-dev' => ['psr-4' => [self::namespace($name).'\\Tests\\' => 'tests/']],
            'extra' => ['webx' => ['theme' => ['title' => $title, ...$manifest, 'uses' => $uses]]],
            'config' => ['sort-packages' => true],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];
    }
}
