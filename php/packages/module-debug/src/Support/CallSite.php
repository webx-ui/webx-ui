<?php

declare(strict_types=1);

namespace WebxUi\Debug\Support;

use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * Where a statement came from: the first frame of the stack outside the framework and outside
 * this package (§7 of the debug bar spec).
 *
 * Laravel and every other vendor are skipped — a statement «from» the query builder says
 * nothing — except `webx-ui/*`, which is the module that asked. A file of the site is named from
 * the site's root. A compiled Blade view is named by its source, read from the `PATH` comment
 * the compiler leaves at its end; a compiled block template by its block type and version.
 */
final class CallSite
{
    /** @var array<string, string>|null real path of a webx-ui package => its short name */
    private ?array $packages = null;

    /** @var array<string, string|null> compiled view => its source */
    private array $compiled = [];

    private ?string $own = null;

    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
    ) {}

    /**
     * The frame that called, from the current stack.
     *
     * @return array{label: string, file: string, line: int}|null
     */
    public function find(): ?array
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;

            if (! is_string($file) || $file === '' || $this->skipped($file)) {
                continue;
            }

            return $this->describe($file, (int) ($frame['line'] ?? 0));
        }

        return null;
    }

    /**
     * How a file is named in the report: `webx-ui/module-blog · src/Feed.php:84`,
     * `app/Http/Controllers/Foo.php:12`, `theme-default · views/layouts/app.blade.php`,
     * `block hero (type v12)`.
     *
     * @return array{label: string, file: string, line: int}
     */
    public function describe(string $file, int $line): array
    {
        $file = self::normal($file);

        $block = $this->blockTemplate($file);

        if ($block !== null) {
            return ['label' => $block, 'file' => $file, 'line' => $line];
        }

        $source = $this->viewSource($file);

        if ($source !== null) {
            return ['label' => $this->name($source), 'file' => $source, 'line' => 0];
        }

        return ['label' => $this->name($file).':'.$line, 'file' => $file, 'line' => $line];
    }

    /** A file as a person looks for it: by package, or from the root of the site. */
    public function name(string $file): string
    {
        $file = self::normal($file);
        $real = self::normal((string) (realpath($file) ?: $file));

        foreach ($this->packages() as $root => $package) {
            if (str_starts_with($real, $root.'/')) {
                return $package.' · '.substr($real, strlen($root) + 1);
            }
        }

        $base = self::normal($this->app->basePath());

        foreach ([$file, $real] as $path) {
            if (str_starts_with($path, $base.'/')) {
                return substr($path, strlen($base) + 1);
            }
        }

        return $file;
    }

    private function skipped(string $file): bool
    {
        $file = self::normal($file);
        $this->own ??= self::normal(dirname(__DIR__));

        if (str_starts_with($file, $this->own.'/')) {
            return true;
        }

        // A compiled view is the template that ran, wherever the storage is (Testbench keeps
        // it under vendor).
        $compiled = $this->config->get('view.compiled');

        if (is_string($compiled) && $compiled !== '' && str_starts_with($file, self::normal($compiled).'/')) {
            return false;
        }

        $vendor = strpos($file, '/vendor/');

        if ($vendor === false) {
            return false;
        }

        // A package of ours is the module that asked — unless it is the renderer's own plumbing,
        // which is named by the block it was drawing instead.
        return ! str_starts_with(substr($file, $vendor + 8), 'webx-ui/');
    }

    /** `block hero (type v12)` for a file the block renderer compiled. */
    private function blockTemplate(string $file): ?string
    {
        $directory = $this->config->get('webx-blocks.compiled');
        // The renderer's default: next to the compiled views.
        $directory = is_string($directory) && $directory !== '' ? self::normal($directory) : self::normal((string) $this->config->get('view.compiled')).'/blocks';

        if (! str_starts_with($file, $directory.'/')) {
            return null;
        }

        if (preg_match('/^(.+)-(\d+)-[0-9a-f]{12}\.php$/', basename($file), $match) === 1) {
            return "block {$match[1]} (type v{$match[2]})";
        }

        if (preg_match('/^(.+)-tmp-[0-9a-f]+\.php$/', basename($file), $match) === 1) {
            return "block {$match[1]} (unsaved template)";
        }

        return 'block template '.basename($file);
    }

    /** The source of a compiled Blade view, from the comment the compiler ends it with. */
    private function viewSource(string $file): ?string
    {
        $compiled = $this->config->get('view.compiled');

        if (! is_string($compiled) || $compiled === '' || ! str_starts_with($file, self::normal($compiled).'/')) {
            return null;
        }

        if (array_key_exists($file, $this->compiled)) {
            return $this->compiled[$file];
        }

        $source = null;
        $size = @filesize($file);
        $handle = @fopen($file, 'rb');

        if ($handle !== false) {
            fseek($handle, max(0, (int) $size - 1024));
            $tail = (string) fread($handle, 1024);
            fclose($handle);

            if (preg_match('#/\*\*PATH (.+?) ENDPATH\*\*/#', $tail, $match) === 1) {
                $source = self::normal($match[1]);
            }
        }

        return $this->compiled[$file] = $source;
    }

    /** @return array<string, string> */
    private function packages(): array
    {
        if ($this->packages !== null) {
            return $this->packages;
        }

        $this->packages = [];

        if (! class_exists(InstalledVersions::class)) {
            return $this->packages;
        }

        // The site itself is a package too, and one made from the skeleton is called
        // `webx-ui/site`: its files are named from its root, not as a package.
        $root = InstalledVersions::getRootPackage()['name'];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            if ($package === $root || ! str_starts_with($package, 'webx-ui/')) {
                continue;
            }

            $path = InstalledVersions::getInstallPath($package);
            $real = is_string($path) ? realpath($path) : false;

            if (is_string($real)) {
                $this->packages[self::normal($real)] = $package;
            }
        }

        // The longest root first, so a package inside another is named by itself.
        uksort($this->packages, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $this->packages;
    }

    private static function normal(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
