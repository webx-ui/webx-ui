<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use Illuminate\Filesystem\Filesystem;
use WebxUi\Compositions\Exceptions\CompositionException;

/**
 * Every composition × handle as a Blade view on disk (COMPOSITIONS §6), and the manifest that
 * says which view a pair is, what it was built from and what was found on the way.
 *
 * A build goes into a directory of its own and becomes the current one by the manifest's rename:
 * a request in the middle reads either the old set or the new, never half of one. A tree that
 * cannot be built throws before anything is written, and the last set stays.
 */
final class Compiler
{
    /** Moves when the generated views change shape, so an old set is compiled again. */
    public const int VERSION = 1;

    public const string NAMESPACE = 'webx-composition';

    public function __construct(
        private readonly Layers $layers,
        private readonly Reader $reader,
        private readonly Probe $probe,
        private readonly Filesystem $files,
        private readonly string $path,
        private readonly string $basePath,
        private readonly string $configPath,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array{version: int, signature: string, build: string, compiled_at: int, views: array<string, array<string, string>>, sources: array<string, int>, findings: list<array{file: string, operation: string, message: string, note: bool}>}
     */
    public function compile(): array
    {
        // Before anything is read: a source saved during the compile is newer than the set (§6.2).
        $compiledAt = time();
        $builder = $this->builder();
        $emitter = new Emitter($this->probe);
        $compositions = $builder->compositions();
        $layouts = $this->layers->layouts();
        $findings = [];
        $views = [];
        $contents = [];

        foreach ($layouts as $handle => $to) {
            if (! in_array($to, $compositions, true)) {
                $findings[] = (new Finding('config/webx-compositions.php', "layouts.{$handle}", "no composition \"{$to}\""))->toArray();
            }
        }

        foreach ($compositions as $composition) {
            $handles = $builder->handles($composition);

            foreach ($layouts as $handle => $to) {
                if ($to === $composition) {
                    $handles[] = $handle;
                }
            }

            foreach (array_unique($handles) as $handle) {
                $tree = $builder->build($composition, $handle);
                $name = self::slug($composition, $handle);
                $contents[$name] = $emitter->view($tree, $composition, $handle);
                $views[$composition][$handle] = self::NAMESPACE.'::'.$name;

                foreach ($tree->findings as $finding) {
                    $findings[(string) $finding] = $finding->toArray();
                }
            }
        }

        // A page type a layout stands in for (§4): the dispatcher of `layout` prints the other one.
        foreach ($layouts as $handle => $to) {
            if (isset($views[$to][$handle], $views['layout'])) {
                $views['layout'][$handle] = $views[$to][$handle];
            }
        }

        $build = 'build-'.str_replace('.', '', uniqid('', true));
        $this->files->ensureDirectoryExists($this->path.'/'.$build);

        foreach ($contents as $name => $content) {
            $this->files->put($this->path.'/'.$build.'/'.$name.'.blade.php', $content);
        }

        $manifest = [
            'version' => self::VERSION,
            'signature' => $this->layers->signature(),
            'build' => $build,
            'compiled_at' => $compiledAt,
            'views' => $views,
            'sources' => $this->sources($builder),
            'findings' => array_values($findings),
        ];

        $this->write('manifest.php', '<?php return '.var_export($manifest, true).';'.PHP_EOL);
        $this->prune($build);

        return $manifest;
    }

    /**
     * The children of one container as a view of their own, for `place` (§6.4), written into the
     * current build the first time it is asked for.
     */
    public function place(string $build, string $composition, string $handle, string $id, bool $added): string
    {
        $name = 'place.'.self::slug($composition, $handle).'__'.str_replace('.', '_', $id).'__'.($added ? 'added' : 'all');
        $file = $this->path.'/'.$build.'/'.str_replace('.', '/', $name).'.blade.php';

        if (! is_file($file)) {
            $tree = $this->builder()->build($composition, $handle);
            $container = $tree->find($id);

            if ($container === null || ! $container->isContainer()) {
                throw new CompositionException("place: no container \"{$id}\" in {$composition} × {$handle}.");
            }

            $this->files->ensureDirectoryExists(dirname($file));
            $this->atomic($file, (new Emitter($this->probe))->children($tree, $container, $added));
        }

        return self::NAMESPACE.'::'.$name;
    }

    /**
     * The manifest on disk, or null when nothing is compiled yet.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $file = $this->path.'/manifest.php';
        $manifest = is_file($file) ? @include $file : null;

        return is_array($manifest) ? $manifest : null;
    }

    public function tree(string $composition, string $handle = 'default'): Tree
    {
        return $this->builder()->build($composition, $handle);
    }

    public function clear(): void
    {
        $this->files->deleteDirectory($this->path);
    }

    /**
     * Whether the set on disk is older than its sources: another set of layers, another config,
     * or a file, a directory of a composition, the installed packages changed since (§6.2).
     *
     * @param  array<string, mixed>  $manifest
     */
    public function stale(array $manifest): bool
    {
        if (($manifest['version'] ?? null) !== self::VERSION || ($manifest['signature'] ?? null) !== $this->layers->signature()) {
            return true;
        }

        $compiledAt = (int) ($manifest['compiled_at'] ?? 0);

        // As Blade does: a source of the same second as the set may be newer than it.
        foreach ((array) ($manifest['sources'] ?? []) as $path => $time) {
            $now = @filemtime((string) $path) ?: 0;

            if ($now !== $time || $now >= $compiledAt) {
                return true;
            }
        }

        return ! is_dir($this->path.'/'.($manifest['build'] ?? ''));
    }

    /** `layout.minimal` × `blog.article` → `layout_minimal__blog_article`: no dots, which a view name reads as slashes. */
    public static function slug(string $composition, string $handle): string
    {
        return str_replace('.', '_', $composition).'__'.str_replace('.', '_', $handle);
    }

    private function builder(): Builder
    {
        return new Builder($this->layers, $this->reader, new Patcher($this->reader));
    }

    /** @return array<string, int> */
    private function sources(Builder $builder): array
    {
        $paths = [$this->configPath, $this->basePath.'/vendor/composer/installed.php'];

        foreach ($builder->sources() as $source) {
            array_push($paths, ...$source->watched());
        }

        $sources = [];

        foreach ($paths as $path) {
            $sources[$path] = @filemtime($path) ?: 0;
        }

        return $sources;
    }

    private function write(string $name, string $content): void
    {
        $this->files->ensureDirectoryExists($this->path);
        $this->atomic($this->path.'/'.$name, $content);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->path.'/'.$name, true);
        }
    }

    private function atomic(string $file, string $content): void
    {
        $temporary = $file.'.'.getmypid().'.tmp';
        $this->files->put($temporary, $content);
        $this->files->move($temporary, $file);
    }

    /** Keeps the current build and the one before it: a request that read the old manifest may still render from it. */
    private function prune(string $current): void
    {
        $builds = array_filter($this->files->directories($this->path), static fn (string $dir): bool => str_starts_with(basename($dir), 'build-') && basename($dir) !== $current);
        usort($builds, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        foreach (array_slice($builds, 1) as $old) {
            $this->files->deleteDirectory($old);
        }
    }
}
