<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Http\Requests\BlockRequest;
use WebxUi\Blocks\Http\Resources\BlockResource;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\Authors;
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
    public function index(Usage $usage): JsonResponse
    {
        $blocks = Block::query()
            ->with(['draftVersion', 'publishedVersion'])
            ->orderBy('sort')
            ->orderBy('slug')
            ->get();

        return ApiResponse::data($this->many($blocks, $usage->counts(), withContent: false));
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
        $block->fill($request->values())->save();

        $content = $request->content();

        if ($content !== null && $this->differs($block, $content)) {
            $block->saveVersion($content, BlockVersion::SOURCE_PANEL, $this->author($request), $request->comment());
        }

        return ApiResponse::data($this->one($block->refresh(), $usage));
    }

    /**
     * A type nobody uses may go; one that stands on a page may not — the page would print a
     * gap, and the editor who opens it would find a block whose form is gone.
     */
    public function destroy(Block $block, Usage $usage): JsonResponse
    {
        $count = $usage->counts()[$block->slug] ?? 0;

        if ($count > 0) {
            return ApiResponse::message((string) __('webx-blocks::page.delete-used', ['count' => $count]), 409);
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

        return $blocks
            ->map(static fn (Block $block): BlockResource => new BlockResource($block, $usage, $authors, $withContent))
            ->values()
            ->all();
    }

    /**
     * Whether what was sent differs from the version being edited. Field by field, on what
     * was sent: a request that carries only the template compares only the template.
     *
     * @param  array<string, mixed>  $content
     */
    private function differs(Block $block, array $content): bool
    {
        $current = $block->currentVersion()?->content();

        if ($current === null) {
            return true;
        }

        foreach ($content as $field => $value) {
            if (($current[$field] ?? null) != $value) {
                return true;
            }
        }

        return false;
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
