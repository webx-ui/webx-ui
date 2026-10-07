<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The views of the site that call a block type by tag — `<x-webx-block type="event-card">` in a
 * listing, a layout, a partial.
 *
 * Those calls live in files, not in any table, so the usage the panel counts from content and
 * from other types' templates never sees them: a type used only by a view looked unused, and
 * deleting it left the view printing nothing in its place. Read from the source with the same
 * reading the graph of types uses ({@see Calls}), so a dynamic `:type` is not followed here either.
 *
 * Where to look is `webx-blocks.views` — the application's `resources/views` by default.
 */
final class ViewCalls
{
    /** Past this the scan stops: a views folder this big is not a site's own templates. */
    private const MAX_FILES = 5000;

    /** @var array<string, list<array{view: string, line: int}>>|null */
    private ?array $calls = null;

    public function __construct(private readonly Config $config) {}

    /**
     * Where a type is called by tag, view by view.
     *
     * @return list<array{view: string, line: int}>
     */
    public function of(string $slug): array
    {
        return $this->all()[$slug] ?? [];
    }

    /**
     * One call as a line an agent can go and open.
     *
     * @param  array{view: string, line: int}  $call
     */
    public static function describe(array $call): string
    {
        return $call['view'].':'.$call['line'];
    }

    /**
     * Every literal call in every view, by type.
     *
     * Once per instance: a delete asks for one type, a listing may ask for all of them, and a
     * views folder does not change in the middle of either.
     *
     * @return array<string, list<array{view: string, line: int}>>
     */
    public function all(): array
    {
        if ($this->calls !== null) {
            return $this->calls;
        }

        $calls = [];
        $seen = 0;

        foreach ($this->roots() as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                if (++$seen > self::MAX_FILES) {
                    break 2;
                }

                $source = (string) @file_get_contents($file->getPathname());

                if (! str_contains($source, '<x-webx-block')) {
                    continue;
                }

                $view = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

                foreach (Calls::withLines($source) as [$slug, $line]) {
                    $calls[$slug][] = ['view' => $view, 'line' => $line];
                }
            }
        }

        return $this->calls = $calls;
    }

    /**
     * @return list<string>
     */
    private function roots(): array
    {
        $configured = $this->config->get('webx-blocks.views');
        $roots = is_array($configured) ? $configured : [function_exists('resource_path') ? resource_path('views') : null];

        return array_values(array_filter(
            array_map(static fn (mixed $root): ?string => is_string($root) ? rtrim($root, '/\\') : null, $roots),
            static fn (?string $root): bool => $root !== null && is_dir($root),
        ));
    }
}
