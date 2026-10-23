<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\FileViewFinder;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Compositions\Exceptions\CompositionException;

/**
 * The compiled compositions on a request (COMPOSITIONS §6): which view a composition × handle is,
 * after one look at whether the set on disk is older than its sources — the look Blade takes at
 * its own views, and only while Blade takes it. Scoped: a long-lived worker looks again per request.
 *
 * The static half is what a module's provider calls:
 *
 *     Compositions::load(__DIR__.'/../resources/compositions');
 *     Compositions::handles('blog', ['blog.article', 'blog.feed']);
 */
class Compositions
{
    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    public function __construct(
        private readonly Compiler $compiler,
        private readonly Factory $views,
        private readonly LoggerInterface $log,
        private readonly bool $check,
    ) {}

    /** A module's `resources/compositions/` (§5.1); a module without the directory adds nothing. */
    public static function load(string $dir, ?string $module = null): void
    {
        Container::getInstance()->make(Layers::class)->module($dir, $module);
    }

    /**
     * The handles a module's pages name (§4), so a file for a handle nobody names is a finding.
     *
     * @param  list<string>  $handles
     */
    public static function handles(string $module, array $handles): void
    {
        Container::getInstance()->make(Layers::class)->handles($module, $handles);
    }

    /**
     * The theme chain, bottom first — the engine of themes hands it over.
     *
     * @param  Closure(): list<array{name: string, dir: string, local: bool}>  $themes
     */
    public static function themes(Closure $themes): void
    {
        Container::getInstance()->make(Layers::class)->themes($themes);
    }

    /**
     * The view of a composition for a handle: the handle's own variant, or `default` when no layer
     * has a file for it.
     */
    public function view(string $composition, ?string $handle = null): string
    {
        $views = (array) ($this->manifest()['views'] ?? []);

        if (! isset($views[$composition])) {
            throw new CompositionException("No layer has a base for the composition \"{$composition}\".");
        }

        $variants = (array) $views[$composition];

        return (string) ($variants[$handle ?? 'default'] ?? $variants['default']);
    }

    /** The view `<x-webx-composition::place>` includes: the children of one container (§6.4). */
    public function place(string $composition, ?string $handle, string $id, bool $added): string
    {
        $manifest = $this->manifest();
        $views = (array) ($manifest['views'][$composition] ?? []);
        $handle = $handle !== null && isset($views[$handle]) ? $handle : 'default';

        return $this->compiler->place((string) $manifest['build'], $composition, $handle, $id, $added);
    }

    /**
     * What the last compile found (§3.6).
     *
     * @return list<Finding>
     */
    public function findings(): array
    {
        return array_map(static fn (array $finding): Finding => Finding::fromArray($finding), (array) ($this->manifest()['findings'] ?? []));
    }

    /**
     * Compiles everything now, whatever the manifest says — `webx:composition:compile`.
     *
     * @return array<string, mixed>
     */
    public function compile(): array
    {
        return $this->manifest = $this->locked(fn (): array => $this->compiler->compile());
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifest = $this->read();

        if ($manifest === null || ($this->check && $this->compiler->stale($manifest))) {
            try {
                $manifest = $this->locked(function (): array {
                    // Another request may have compiled while this one waited for the lock.
                    $fresh = $this->read();

                    return $fresh !== null && ! $this->compiler->stale($fresh) ? $fresh : $this->compiler->compile();
                });

                foreach ((array) $manifest['findings'] as $finding) {
                    $this->log->warning('Composition: '.Finding::fromArray($finding));
                }
            } catch (Throwable $e) {
                // The last set keeps serving: a site does not go down over a typo in a patch.
                if ($manifest === null) {
                    throw $e;
                }

                $this->log->error('Compositions were not compiled: '.$e->getMessage());
            }
        }

        $this->point((string) $manifest['build']);

        return $this->manifest = $manifest;
    }

    /**
     * The namespace of the compiled views at one build. The finder remembers where it found a
     * view, so a move to a new build forgets that — only when the build is another one.
     */
    private function point(string $build): void
    {
        $path = $this->compiler->path().'/'.$build;
        $finder = $this->views->getFinder();

        if ($finder instanceof FileViewFinder && ($finder->getHints()[Compiler::NAMESPACE] ?? null) === [$path]) {
            return;
        }

        $this->views->replaceNamespace(Compiler::NAMESPACE, [$path]);
        $finder->flush();
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        return $this->compiler->current();
    }

    /**
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function locked(Closure $work): array
    {
        @mkdir($this->compiler->path(), 0777, true);
        $lock = fopen($this->compiler->path().'/compile.lock', 'c');

        try {
            if ($lock !== false) {
                flock($lock, LOCK_EX);
            }

            $manifest = $work();
        } finally {
            if ($lock !== false) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $this->point((string) $manifest['build']);

        return $manifest;
    }
}
