<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\Http\Resources\BlockResource;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Blocks\Panel\Usage;

/**
 * "Customise" on a place a module declared (§4.2 of the components spec): a component of that
 * slug, started from what the site prints there now.
 *
 * The template is the source of the fallback view as the site resolves it — the copy a site
 * published into `resources/views/vendor/` when there is one, the module's own otherwise — so
 * the editor starts from what visitors see rather than from the module's default. It is a
 * draft and stays one: until it is published the partial keeps printing, and the editor works
 * on the stage in the meantime. The module's styles are not copied: they live in the module's
 * shared stylesheet and keep applying to the same classes.
 */
final class ComponentController
{
    public function customise(Request $request, string $slug, BlockComponents $components, Views $views, Filesystem $files, Usage $usage): JsonResponse
    {
        $declared = $components->get($slug) ?? throw new NotFoundHttpException;

        $existing = Block::query()->where('slug', $slug)->first();

        if ($existing instanceof Block) {
            return new JsonResponse([
                'message' => (string) __('webx-blocks::calls.customise-exists'),
                'id' => $existing->id,
            ], 409);
        }

        $template = $files->get($views->getFinder()->find($declared['fallback']));

        $block = Block::query()->create([
            'slug' => $slug,
            'kind' => Block::KIND_COMPONENT,
            'title' => $declared['title'],
            'description' => $declared['description'],
        ]);

        $id = $request->user()?->getAuthIdentifier();

        $block->saveVersion(
            [
                'schema' => $declared['schema'],
                'template' => $template,
                'sample' => $components->sample($slug),
            ],
            BlockVersion::SOURCE_PANEL,
            is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null,
            (string) __('webx-blocks::calls.customised-from', ['view' => $declared['fallback']]),
        );

        $block->refresh()->loadMissing(['draftVersion', 'publishedVersion']);

        return ApiResponse::data(
            new BlockResource($block, $usage->counts(), Authors::names([$block->draftVersion?->author_id])),
            201,
        );
    }
}
