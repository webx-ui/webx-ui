<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Http\Resources\BlockResource;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Blocks\Panel\Customiser;
use WebxUi\Blocks\Panel\Usage;

/**
 * "Customise" on a place a module declared (§4.2 of the components spec): a component of that
 * slug, started from what the site prints there now. The work is {@see Customiser}'s — an
 * agent's `blocks_create` on a declared slug does the same.
 */
final class ComponentController
{
    public function customise(Request $request, string $slug, Customiser $customiser, Usage $usage): JsonResponse
    {
        if (! $customiser->declared($slug)) {
            throw new NotFoundHttpException;
        }

        $existing = Block::query()->where('slug', $slug)->first();

        if ($existing instanceof Block) {
            return new JsonResponse([
                'message' => (string) __('webx-blocks::calls.customise-exists'),
                'id' => $existing->id,
            ], 409);
        }

        $id = $request->user()?->getAuthIdentifier();

        $block = $customiser->customise(
            $slug,
            BlockVersion::SOURCE_PANEL,
            is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null,
        );

        return ApiResponse::data(
            new BlockResource($block, $usage->counts(), Authors::names([$block->draftVersion?->author_id])),
            201,
        );
    }
}
