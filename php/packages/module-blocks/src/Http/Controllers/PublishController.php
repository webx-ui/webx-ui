<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Http\Resources\BlockResource;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Blocks\Panel\Publisher;
use WebxUi\Blocks\Panel\PublishFailed;
use WebxUi\Blocks\Panel\Usage;

/**
 * Publishing is its own action (§2), and it can be refused (§15). A refusal is a 422 in the
 * shape the panel already reads — the message under `template` — with the line and, when a
 * page rather than the sample broke, which page.
 */
final class PublishController
{
    public function __invoke(Block $block, Publisher $publisher, Usage $usage): JsonResponse
    {
        try {
            $publisher->publish($block);
        } catch (PublishFailed $failed) {
            return new JsonResponse([
                'message' => (string) __('webx-blocks::page.publish-failed'),
                'errors' => ['template' => [$failed->failure->reason]],
                'line' => $failed->failure->templateLine,
                'entity' => $failed->entity,
            ], 422);
        } catch (BlocksException) {
            return ApiResponse::message((string) __('webx-blocks::page.no-draft'), 409);
        }

        $block->refresh()->loadMissing(['draftVersion', 'publishedVersion']);
        $authors = Authors::names([$block->draftVersion?->author_id, $block->publishedVersion?->author_id]);

        return ApiResponse::data(new BlockResource($block, $usage->counts(), $authors));
    }
}
