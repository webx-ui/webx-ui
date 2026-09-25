<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blocks\Models\Block;

/**
 * `POST blocks/reorder { ids }`: the order of the types in the list and in the picker.
 *
 * The ids are usually one group, dragged inside its section — not the whole catalogue. So they
 * take the places they already held, in the new order, and every other type stays where it
 * was: a group rearranged does not jump ahead of the one above it. Then the list is numbered
 * again from one, which also mends positions two types happened to share.
 *
 * `position` only — never `sort`, which is the order of the styles on the page.
 */
final class ReorderController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{ids: list<int|string>} $valid */
        $valid = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:'.(new Block)->getTable().',id'],
        ]);

        $given = array_map(intval(...), $valid['ids']);

        DB::transaction(static function () use ($given): void {
            /** @var list<int> $all */
            $all = Block::query()->orderBy('position')->orderBy('slug')->pluck('id')->map(intval(...))->all();
            $next = $given;

            foreach ($all as $index => $id) {
                if (in_array($id, $given, true)) {
                    $all[$index] = array_shift($next) ?? $id;
                }
            }

            foreach ($all as $index => $id) {
                Block::query()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return ApiResponse::data(['ids' => $given]);
    }
}
