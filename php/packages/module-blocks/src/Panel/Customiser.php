<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\Models\Block;

/**
 * "Customise" on a place a module declared (§4.2 of the components spec) — the one way to start
 * one, for the panel's button and for `blocks_create` on a declared slug alike.
 *
 * The template is the source of the fallback view as the site resolves it — the copy a site
 * published into `resources/views/vendor/` when there is one, the module's own otherwise — so
 * the editor starts from what visitors see rather than from the module's default. It is a
 * draft and stays one: until it is published the partial keeps printing. The module's styles
 * are not copied: they live in the module's shared stylesheet and keep applying to the same
 * classes.
 */
final class Customiser
{
    public function __construct(
        private readonly BlockComponents $components,
        private readonly Views $views,
        private readonly Filesystem $files,
    ) {}

    public function declared(string $slug): bool
    {
        return $this->components->has($slug);
    }

    /**
     * The new type with its first draft. The caller has made sure the slug is declared and free.
     */
    public function customise(string $slug, string $source, ?int $authorId): Block
    {
        $declared = $this->components->get($slug) ?? throw new InvalidArgumentException("Nothing declares [{$slug}].");

        $template = $this->files->get($this->views->getFinder()->find($declared['fallback']));

        $block = Block::query()->create([
            'slug' => $slug,
            'kind' => Block::KIND_COMPONENT,
            'title' => (string) BlockComponents::words($declared['title']),
            'description' => BlockComponents::words($declared['description']),
        ]);

        $block->saveVersion(
            [
                'schema' => $declared['schema'],
                'template' => $template,
                'sample' => $this->components->sample($slug),
            ],
            $source,
            $authorId,
            (string) __('webx-blocks::calls.customised-from', ['view' => $declared['fallback']]),
        );

        return $block->refresh()->loadMissing(['draftVersion', 'publishedVersion']);
    }
}
