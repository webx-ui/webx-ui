<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests\Concerns;

use Illuminate\Filesystem\Filesystem;

/**
 * The site files a theme command edits — `.env`, `composer.json`, `phpunit.xml` and the theme
 * directories — written into Testbench's application for one test and put back after it.
 * `composer.json` is the skeleton's own and is restored byte for byte; the rest did not exist.
 */
trait SiteSandbox
{
    /** @var array<string, string|null> What each touched file held before the test; null — absent. */
    private array $sandboxed = [];

    /** @var list<string> */
    private array $sandboxDirectories = [];

    /** @param  list<string>  $directories  Relative to the base path: removed before and after. */
    protected function sandbox(string $base, array $directories): void
    {
        $files = new Filesystem;

        foreach (['.env', 'composer.json', 'phpunit.xml'] as $file) {
            $path = $base.'/'.$file;
            $this->sandboxed[$path] = $files->exists($path) ? (string) $files->get($path) : null;
        }

        foreach ($directories as $directory) {
            $this->sandboxDirectories[] = $base.'/'.$directory;
            $files->deleteDirectory($base.'/'.$directory);
        }
    }

    protected function restoreSandbox(): void
    {
        $files = new Filesystem;

        foreach ($this->sandboxed as $path => $contents) {
            $contents === null ? $files->delete($path) : $files->put($path, $contents);
        }

        foreach ($this->sandboxDirectories as $directory) {
            $files->deleteDirectory($directory);
        }

        $this->sandboxed = [];
        $this->sandboxDirectories = [];
    }
}
