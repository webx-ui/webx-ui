<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\Http\Requests\BlockRequest;
use WebxUi\Blocks\Http\Resources\BlockResource;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\Graph;
use WebxUi\Blocks\Panel\Usage;

/**
 * The types: the list the section opens on, the catalogue the constructor reads, and the
 * row itself. Saving writes a version (§2) — every save, because a block is code and its
 * history is an audit log — unless nothing in the content changed, in which case there is
 * nothing to log.
 *
 * No pagination: a site has a dozen types, and the list is cards with thumbnails, not a
 * table. The whole of it is one response.
 */
final class BlockController
{
    /**
     * Every type, and beside them the places modules declared (§3.10 of the components spec) —
     * the ones nobody customised yet are cards of their own on the screen, or nobody would know
     * they can be.
     */
    public function index(Usage $usage, BlockComponents $components): JsonResponse
    {
        $blocks = Block::query()
            ->with(['draftVersion', 'publishedVersion'])
            ->orderBy('sort')
            ->orderBy('slug')
            ->get();

        $slugs = $blocks->pluck('slug')->all();

        return new JsonResponse([
            'data' => $this->many($blocks, $usage->counts(), withContent: false),
            'declared' => array_map(
                static fn (array $declared): array => BlockResource::declaration($declared, in_array($declared['slug'], $slugs, true)),
                $components->all(),
            ),
        ]);
    }

    /**
     * What the picker and the constructor need: every type with a published version — the
     * disabled ones too, because a block already on a page keeps its form even after its
     * type was hidden from the picker.
     */
    public function catalog(Usage $usage): JsonResponse
    {
        $blocks = Block::query()
            ->published()
            // A component is called by templates, never put in content: nothing to pick.
            ->where('kind', Block::KIND_BLOCK)
            ->with(['draftVersion', 'publishedVersion'])
            ->orderBy('sort')
            ->orderBy('slug')
            ->get();

        return ApiResponse::data($this->many($blocks, $usage->counts(), withContent: true));
    }

    public function show(Block $block, Usage $usage): JsonResponse
    {
        return ApiResponse::data($this->one($block, $usage));
    }

    public function store(BlockRequest $request, Usage $usage): JsonResponse
    {
        $block = Block::query()->create($request->values());

        // Version 1 straight away, empty or not: the editor opens on a draft, and everything
        // else — the preview, the history, an agent — expects one to exist.
        $block->saveVersion($request->content() ?? [], BlockVersion::SOURCE_PANEL, $this->author($request), $request->comment());

        return ApiResponse::data($this->one($block->refresh(), $usage), 201);
    }

    public function update(BlockRequest $request, Block $block, Usage $usage): JsonResponse
    {
        $values = $request->values();
        $refusal = BlockInput::kindRefusal($block, $values['kind'] ?? null, $usage->counts());

        if ($refusal !== null) {
            throw ValidationException::withMessages(['kind' => $refusal]);
        }

        $block->fill($values)->save();

        $content = $request->content();

        if ($content !== null && $block->contentDiffers($content)) {
            $block->saveVersion($content, BlockVersion::SOURCE_PANEL, $this->author($request), $request->comment());
        }

        return ApiResponse::data($this->one($block->refresh(), $usage));
    }

    /**
     * A type nobody uses may go; one that stands on a page may not — the page would print a
     * gap, and the editor who opens it would find a block whose form is gone.
     */
    public function destroy(Block $block, Usage $usage, Graph $graph): JsonResponse
    {
        // A type that other types call may not go either (§3.6 of the components spec): their
        // templates would print a gap where it stood. A module's declared place is not a parent
        // — it has its fallback, and deleting is exactly how it goes back to it.
        $parents = $graph->usedBy($block->slug);

        if ($parents !== []) {
            return new JsonResponse([
                'message' => (string) __('webx-blocks::calls.delete-used-by'),
                'errors' => ['used_by' => array_map(
                    static fn (array $parent): string => (string) __('webx-blocks::calls.delete-used-by-one', ['title' => $parent['title'], 'slug' => $parent['slug']]),
                    $parents,
                )],
                'used_by' => $parents,
            ], 422);
        }

        $count = $usage->counts()[$block->slug] ?? 0;

        if ($count > 0) {
            // One is one: the line with the number in it reads "on 1 pages" otherwise.
            $line = $count === 1
                ? __('webx-blocks::page.delete-used-one')
                : __('webx-blocks::page.delete-used', ['count' => $count]);

            return ApiResponse::message((string) $line, 409);
        }

        $block->delete();

        return ApiResponse::noContent();
    }

    private function one(Block $block, Usage $usage): BlockResource
    {
        $block->loadMissing(['draftVersion', 'publishedVersion']);

        $authors = Authors::names([$block->draftVersion?->author_id, $block->publishedVersion?->author_id]);

        return new BlockResource($block, $usage->counts(), $authors, withContent: true);
    }

    /**
     * @param  Collection<int, Block>  $blocks
     * @param  array<string, int>  $usage
     * @return list<BlockResource>
     */
    private function many(Collection $blocks, array $usage, bool $withContent): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            $ids[] = $block->draftVersion?->author_id;
            $ids[] = $block->publishedVersion?->author_id;
        }

        $authors = Authors::names($ids);

        $parents = (new Graph)->parents();

        return $blocks
            ->map(static fn (Block $block): BlockResource => new BlockResource($block, $usage, $authors, $withContent, $parents))
            ->values()
            ->all();
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
