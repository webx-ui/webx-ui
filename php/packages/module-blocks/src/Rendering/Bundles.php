<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\HtmlString;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Models\BlockBundle;

/**
 * The styles and scripts of a page, as one file each.
 *
 * Which types a page uses is known from its tree; their versions are known from the registry.
 * The pairs, in `sort` then `slug` order, make a hash; the hash names a row of `block_bundles`
 * that holds the glued CSS and JS and is written the first time that set is seen. A page pulls
 * only what stands on it, pages with the same set share the file, and because the hash names
 * the versions the file is cached forever: publishing a new version changes the hash only where
 * the block stands.
 *
 * Glued in `sort` order rather than alphabetically, so that two blocks arguing over one rule are
 * settled by their author.
 */
final class Bundles
{
    private const RUNTIME = __DIR__.'/../../resources/js/runtime.js';

    private ?string $runtime = null;

    public function __construct(
        private readonly BlockTypes $types,
        private readonly Renderer $renderer,
        private readonly Config $config,
        private readonly UrlGenerator $url,
    ) {}

    /**
     * What `@webxBlocks` prints: the tags of the bundle of everything this response has
     * rendered so far.
     *
     * Evaluated when the head is printed, not before — with `@extends` and with components the
     * content renders before the layout, so by the time `<head>` is written the renderer knows
     * every block below it. `all` prints both tags; `styles` and `scripts` split them between the
     * head and the end of the body; `runtime` prints the runtime alone, for a site whose own
     * entry calls `webx.provide` and needs `webx` to exist before it runs.
     */
    public function tags(string $what = 'all'): HtmlString
    {
        if ($what === 'runtime') {
            return new HtmlString(sprintf('<script src="%s"></script>', e($this->runtimeUrl())));
        }

        $bundle = $this->build($this->renderer->usedTypes());

        if (! $bundle instanceof BlockBundle) {
            return new HtmlString('');
        }

        $inline = $this->inlineBelow() > 0 && strlen($bundle->css) + strlen($bundle->js ?? '') <= $this->inlineBelow();
        $html = '';

        if ($what !== 'scripts' && $bundle->css !== '') {
            $html .= $inline
                ? '<style>'.self::safe($bundle->css).'</style>'
                : sprintf('<link rel="stylesheet" href="%s">', e($this->url->route('webx.blocks.css', ['hash' => $bundle->hash])));
        }

        if ($what !== 'styles' && $bundle->js !== null) {
            $html .= $inline
                ? '<script type="module">'.self::safe($bundle->js).'</script>'
                : sprintf('<script type="module" src="%s"></script>', e($this->url->route('webx.blocks.js', ['hash' => $bundle->hash])));
        }

        return new HtmlString($html);
    }

    /**
     * The bundle of a set of types, written if it is not there yet. Null when there is nothing
     * to serve: no types, or types with neither styles nor script.
     *
     * @param  iterable<array-key, BlockType>  $types
     */
    public function build(iterable $types): ?BlockBundle
    {
        $ordered = $this->order($types);

        if ($ordered === []) {
            return null;
        }

        $hash = $this->hash($ordered);
        $bundle = BlockBundle::query()->find($hash);

        if ($bundle instanceof BlockBundle) {
            return $bundle;
        }

        $css = '';
        $scripts = '';

        foreach ($ordered as $type) {
            if (trim($type->styles) !== '') {
                $css .= sprintf("/* %s v%d */\n%s\n", $type->slug, $type->version, trim($type->styles));
            }

            if ($type->script !== null && trim($type->script) !== '') {
                $scripts .= sprintf("webx.block(%s, async (el, values) => {\n%s\n});\n", json_encode($type->slug, JSON_THROW_ON_ERROR), trim($type->script));
            }
        }

        if ($css === '' && $scripts === '') {
            return null;
        }

        $attributes = [
            'hash' => $hash,
            'types' => array_map(static fn (BlockType $type): array => [$type->slug, $type->version], $ordered),
            'css' => $css,
            'js' => $scripts === '' ? null : $this->runtime()."\n".$scripts,
        ];

        try {
            return BlockBundle::query()->create($attributes);
        } catch (UniqueConstraintViolationException) {
            // Two requests met the same new set at once; the other one wrote it.
            return BlockBundle::query()->findOrFail($hash);
        }
    }

    /**
     * The bundle of the published versions of some slugs — what an entity needs, for warming.
     *
     * @param  iterable<array-key, string>  $slugs
     */
    public function forSlugs(iterable $slugs): ?BlockBundle
    {
        $types = [];

        foreach ($slugs as $slug) {
            $type = $this->types->find((string) $slug);

            if ($type instanceof BlockType) {
                $types[] = $type;
            }
        }

        return $this->build($types);
    }

    public function find(string $hash): ?BlockBundle
    {
        return BlockBundle::query()->find($hash);
    }

    /**
     * Drop the bundles glued from a version that is no longer the published one.
     *
     * Nothing renders them any more: the page that used to name such a hash names another one
     * since the version was published. Only HTML cached outside the application — a CDN, a page
     * cache — could still ask for one, and that is what the long `max-age` was for.
     *
     * @return int how many were dropped
     */
    public function prune(): int
    {
        $dropped = 0;

        /** @var BlockBundle $bundle */
        foreach (BlockBundle::query()->cursor() as $bundle) {
            foreach ($bundle->versions() as $slug => $version) {
                if ($this->types->find($slug)?->version !== $version) {
                    $bundle->delete();
                    $dropped++;

                    break;
                }
            }
        }

        return $dropped;
    }

    /**
     * The hash of an ordered set: sixteen hex characters of the pairs of slug and version.
     *
     * @param  list<BlockType>  $ordered
     */
    public function hash(array $ordered): string
    {
        $pairs = array_map(static fn (BlockType $type): array => [$type->slug, $type->version], $ordered);

        return substr(hash('sha256', json_encode($pairs, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /** The runtime, as shipped with the package. */
    public function runtime(): string
    {
        return $this->runtime ??= trim((string) file_get_contents(self::RUNTIME));
    }

    /** Changes with the runtime's content, so its URL can be cached forever. */
    public function runtimeVersion(): string
    {
        return substr(hash('sha256', $this->runtime()), 0, 8);
    }

    public function runtimeUrl(): string
    {
        return $this->url->route('webx.blocks.runtime', ['v' => $this->runtimeVersion()]);
    }

    /**
     * By `sort`, then by `slug`, one entry per slug.
     *
     * @param  iterable<array-key, BlockType>  $types
     * @return list<BlockType>
     */
    private function order(iterable $types): array
    {
        $unique = [];

        foreach ($types as $type) {
            $unique[$type->slug] = $type;
        }

        $ordered = array_values($unique);

        usort($ordered, static fn (BlockType $a, BlockType $b): int => [$a->sort, $a->slug] <=> [$b->sort, $b->slug]);

        return $ordered;
    }

    private function inlineBelow(): int
    {
        return max(0, (int) $this->config->get('webx-blocks.bundles.inline_below', 0));
    }

    /** Inline content must not be able to close its own tag. */
    private static function safe(string $code): string
    {
        return str_replace('</', '<\/', $code);
    }
}
