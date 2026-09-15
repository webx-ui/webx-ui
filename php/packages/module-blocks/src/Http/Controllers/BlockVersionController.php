<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Http\Resources\BlockResource;
use WebxUi\Blocks\Http\Resources\BlockVersionResource;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Blocks\Panel\Usage;

/**
 * The history of a type. Restoring is not "put it back": the old version becomes a new
 * draft, and publishing it is the same separate step it always is (§11) — so the audit log
 * shows the restore as what it was, and the site changes only when somebody says so.
 */
final class BlockVersionController
{
    public function index(Block $block): JsonResponse
    {
        $versions = $block->versions()->get();
        $authors = Authors::names($versions->map(static fn (BlockVersion $version): ?int => $version->author_id));

        return ApiResponse::data(
            $versions
                ->map(static fn (BlockVersion $version): BlockVersionResource => new BlockVersionResource($version, $authors))
                ->values()
                ->all(),
        );
    }

    public function show(Block $block, int $number): JsonResponse
    {
        $version = $this->version($block, $number);

        return ApiResponse::data(new BlockVersionResource($version, Authors::names([$version->author_id]), withContent: true));
    }

    public function restore(Request $request, Block $block, int $number, Usage $usage): JsonResponse
    {
        $version = $this->version($block, $number);
        $id = $request->user()?->getAuthIdentifier();

        $block->saveVersion(
            $version->content(),
            BlockVersion::SOURCE_PANEL,
            is_int($id) ? $id : null,
            (string) __('webx-blocks::page.restored-from', ['number' => $version->number]),
        );

        $block->refresh()->loadMissing(['draftVersion', 'publishedVersion']);
        $authors = Authors::names([$block->draftVersion?->author_id, $block->publishedVersion?->author_id]);

        return ApiResponse::data(new BlockResource($block, $usage->counts(), $authors));
    }

    private function version(Block $block, int $number): BlockVersion
    {
        $version = $block->versions()->where('number', $number)->first();

        return $version instanceof BlockVersion ? $version : throw new NotFoundHttpException;
    }
}
