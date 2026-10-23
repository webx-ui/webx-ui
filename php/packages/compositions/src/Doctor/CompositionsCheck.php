<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Doctor;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\FileViewFinder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Compositions\Compiler;
use WebxUi\Compositions\Inspector;

/**
 * The compositions in `webx:doctor` (COMPOSITIONS §8): whether the trees build, whether what the
 * patches name is there, and whether the set a request serves is the one the sources give.
 *
 * The last is the deploy's question. With `view.check_cache_timestamps` on, a request compiles
 * a stale set again and nothing is wrong; with it off — production after `view:cache` — the
 * set stays as the last `webx:boot` left it, and a theme's patch that came with the deploy does
 * nothing until somebody compiles.
 *
 * Registered only where the panel's frame is installed: the engine does not need it.
 */
final class CompositionsCheck implements Check
{
    private const string SUBJECT = 'Compositions';

    public function __construct(
        private readonly Inspector $inspector,
        private readonly Compiler $compiler,
        private readonly Repository $config,
        private readonly Factory $views,
    ) {}

    public function run(): array
    {
        $compositions = $this->inspector->compositions();
        $unreadable = $this->inspector->unreadable();

        if ($compositions === [] && $unreadable === []) {
            return [];
        }

        $found = [];

        foreach ($unreadable as $failure) {
            $found[] = Diagnosis::fail(self::SUBJECT, "{$failure['message']} The last compiled set keeps serving: mend the file, then php artisan webx:composition:compile.");
        }

        $views = 0;

        if ($unreadable === []) {
            foreach ($this->inspector->all() as $pair) {
                if ($pair['tree'] === null) {
                    $found[] = Diagnosis::fail(self::SUBJECT, "{$pair['composition']} × {$pair['handle']} does not build — {$pair['error']} The last compiled set keeps serving.");
                } else {
                    $views++;
                }
            }

            foreach ($this->inspector->findings() as $finding) {
                if (! $finding->note) {
                    $found[] = Diagnosis::warn(self::SUBJECT, "{$finding} — the operation is skipped; php artisan webx:theme:check says it with the rest.");
                }
            }
        }

        $found = [...$found, ...$this->freshness(), ...$this->handles()];

        if ($found !== []) {
            return $found;
        }

        return [Diagnosis::ok(self::SUBJECT, sprintf(
            '%d %s of %s, compiled and as the sources give them.',
            $views,
            $views === 1 ? 'view' : 'views',
            implode(', ', $compositions),
        ))];
    }

    /** @return list<Diagnosis> */
    private function freshness(): array
    {
        $manifest = $this->compiler->current();
        $setting = $this->config->get('webx-compositions.check');
        $check = is_bool($setting) ? $setting : (bool) $this->config->get('view.check_cache_timestamps', true);

        if ($manifest === null) {
            return [Diagnosis::note(self::SUBJECT, 'not compiled yet: the first page compiles them. php artisan webx:boot does it on a deploy (webx:composition:compile alone).')];
        }

        if (! $this->compiler->stale($manifest)) {
            return [];
        }

        return $check
            ? [Diagnosis::note(self::SUBJECT, 'the compiled set is older than its sources; the next page compiles it again, as Blade does its views.')]
            : [Diagnosis::warn(self::SUBJECT, 'the compiled set is older than its sources, and the mtime check is off (view.check_cache_timestamps): pages print the old trees. Run php artisan webx:composition:compile — a step of webx:boot.')];
    }

    /**
     * A module's page that calls the layout without `handle`: it gets `default`, and a theme's
     * file for the page type never applies to it.
     *
     * @return list<Diagnosis>
     */
    private function handles(): array
    {
        $finder = $this->views->getFinder();

        if (! $finder instanceof FileViewFinder) {
            return [];
        }

        $found = [];

        foreach ($finder->getHints() as $namespace => $paths) {
            if (! str_starts_with((string) $namespace, 'webx-') || $namespace === Compiler::NAMESPACE) {
                continue;
            }

            foreach ($paths as $path) {
                if (! is_dir($path)) {
                    continue;
                }

                /** @var SplFileInfo $info */
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $info) {
                    $source = str_ends_with($info->getFilename(), '.blade.php') ? (string) @file_get_contents($info->getPathname()) : '';

                    if (preg_match_all('/<x-dynamic-component\s[^>]*:component="config\(\'webx-[a-z-]+\.layout\'\)[^>]*>/', $source, $tags) === false) {
                        continue;
                    }

                    foreach ($tags[0] as $tag) {
                        if (preg_match('/\s:?handle=/', $tag) !== 1) {
                            $view = $namespace.'::'.str_replace('/', '.', substr(str_replace('\\', '/', $info->getPathname()), strlen(rtrim($path, '/')) + 1, -10));
                            $found[$view] = Diagnosis::note(self::SUBJECT, "{$view} calls the layout without handle=\"…\": it gets the default tree, and a theme's file for its page type never applies.");
                        }
                    }
                }
            }
        }

        return array_values($found);
    }
}
