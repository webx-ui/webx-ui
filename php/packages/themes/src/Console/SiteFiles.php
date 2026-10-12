<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Filesystem\Filesystem;

/**
 * The site's own files a theme command touches: `.env`, `composer.json`, `phpunit.xml` and, only
 * by advice, `vite.config.js`.
 *
 * Each edit is the narrowest one that does the job, and only on a file in the ordinary shape a
 * new site has; anything less ordinary is somebody's own arrangement, and they are told what to
 * change instead. Composer and npm are never run from here: they are long, go to the network and
 * live in different places on every machine, so the commands say which to run.
 */
final class SiteFiles
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $basePath,
    ) {}

    /**
     * The theme's tests run with the site's own, so `php artisan test` covers them. A suite
     * before `</testsuites>`, and only in a file that has that tag once and no mention of the
     * directory.
     *
     * @return string|null What happened or what to do by hand; null when there is no phpunit.xml or it already runs them.
     */
    public function addTestSuite(string $relative): ?string
    {
        $phpunit = $this->path('phpunit.xml');

        if (! $this->files->exists($phpunit)) {
            return null;
        }

        $contents = (string) $this->files->get($phpunit);

        if (str_contains($contents, "<directory>{$relative}/tests</directory>")) {
            return null;
        }

        if (substr_count($contents, '</testsuites>') !== 1) {
            return "add a test suite for {$relative}/tests";
        }

        $suite = '$1    <testsuite name="Theme">'."\n"
            .'$1        <directory>'.$relative.'/tests</directory>'."\n"
            .'$1    </testsuite>'."\n"
            .'$1</testsuites>';

        $this->files->put($phpunit, preg_replace('~^([ \t]*)</testsuites>~m', $suite, $contents, 1) ?? $contents);

        return "runs {$relative}/tests";
    }

    /** The suite {@see addTestSuite()} wrote, gone with the directory it ran. True when it was there. */
    public function removeTestSuite(string $relative): bool
    {
        $phpunit = $this->path('phpunit.xml');

        if (! $this->files->exists($phpunit)) {
            return false;
        }

        $contents = (string) $this->files->get($phpunit);
        $pattern = '~^[ \t]*<testsuite name="[^"]*">\s*<directory>'.preg_quote($relative, '~').'/tests</directory>\s*</testsuite>[ \t]*\R~m';
        $updated = preg_replace($pattern, '', $contents, 1);

        if ($updated === null || $updated === $contents) {
            return false;
        }

        $this->files->put($phpunit, $updated);

        return true;
    }

    /**
     * The entries of a local theme the site's Vite has to build, or no longer has to.
     *
     * @param  list<string>  $entries  Paths under the theme (`src/css/theme.css`).
     * @return list<string> One line of advice per entry to add or to remove.
     */
    public function viteAdvice(string $relative, array $entries, bool $add): array
    {
        $config = $this->path('vite.config.js');
        $contents = $this->files->exists($config) ? (string) $this->files->get($config) : '';
        $advice = [];

        // The skeleton's config lists the theme's entries and leaves out the ones that are not
        // there (`.filter((entry) => existsSync(entry))`): an entry gone with its theme needs no edit.
        if (! $add && str_contains($contents, 'existsSync(entry)')) {
            return [];
        }

        foreach ($entries as $entry) {
            $listed = str_contains($contents, "{$relative}/{$entry}");

            if ($add && ! $listed) {
                $advice[] = "add '{$relative}/{$entry}' to the laravel() plugin's input";
            } elseif (! $add && $listed) {
                $advice[] = "remove '{$relative}/{$entry}' from the laravel() plugin's input";
            }
        }

        return $advice;
    }

    /**
     * `KEY=value` in `.env`: the line replaced where the key is, appended where it is not.
     * False without a `.env` — a site configured another way is told the line instead.
     */
    public function setEnv(string $key, string $value): bool
    {
        $env = $this->path('.env');

        if (! $this->files->exists($env)) {
            return false;
        }

        $contents = (string) $this->files->get($env);
        $line = "{$key}={$value}";
        $pattern = '~^'.preg_quote($key, '~').'=.*$~m';

        $contents = preg_match($pattern, $contents) === 1
            ? (string) preg_replace($pattern, $line, $contents, 1)
            : rtrim($contents, "\r\n")."\n{$line}\n";

        $this->files->put($env, $contents);

        return true;
    }

    /**
     * A path repository for a package that lives inside the site, and the package required —
     * what `composer require` would write, without Composer. False without a composer.json.
     */
    public function requireLocalPackage(string $name, string $relative): bool
    {
        $file = $this->path('composer.json');

        if (! $this->files->exists($file)) {
            return false;
        }

        // Objects, not arrays: an empty `"require-dev": {}` must stay an object on the way back.
        $composer = json_decode((string) $this->files->get($file));

        if (! $composer instanceof \stdClass) {
            return false;
        }

        // A list of repositories is the usual shape; Composer also takes an object keyed by name.
        $repositories = $composer->repositories ?? [];
        $listed = false;

        foreach ((array) $repositories as $repository) {
            if (is_object($repository) && ($repository->type ?? null) === 'path' && rtrim((string) ($repository->url ?? ''), '/') === $relative) {
                $listed = true;
            }
        }

        if (! $listed) {
            $entry = (object) ['type' => 'path', 'url' => $relative, 'options' => (object) ['symlink' => true]];

            if (is_array($repositories)) {
                $repositories[] = $entry;
            } elseif ($repositories instanceof \stdClass) {
                $repositories->{$name} = $entry;
            }

            $composer->repositories = $repositories;
        }

        $composer->require ??= new \stdClass;
        $composer->require->{$name} = '@dev';

        $this->files->put($file, ThemePackage::json($composer));

        return true;
    }

    private function path(string $file): string
    {
        return rtrim($this->basePath, '/\\').'/'.$file;
    }
}
