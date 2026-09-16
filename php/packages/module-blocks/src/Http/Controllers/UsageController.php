<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Panel\Usage;

/** Where a type stands: the entities, named. */
final class UsageController
{
    public function __invoke(Block $block, Usage $usage): JsonResponse
    {
        return ApiResponse::data($usage->of($block->slug));
    }
}
