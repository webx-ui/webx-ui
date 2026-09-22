<?php

declare(strict_types=1);

namespace WebxUi\Menu\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;
use WebxUi\Menu\Tree\Placement;

/**
 * Where an item sits: under which item, and how far down (§10).
 *
 * A parent and a position rather than a target and a zone, because the gesture this answers is
 * one drag into one list: the browser already knows which list the row landed in and where in
 * it, and asking it to describe that as "after this sibling" would be asking it to work out
 * something it had and threw away.
 *
 * The item is read again before it is moved and the bounds are never used for arithmetic here:
 * an item loaded a moment ago holds the bounds it had then, and a tree that has been dragged
 * twice is a tree where those are stale (§10).
 */
final class MenuItemMoveController
{
    public function __invoke(Request $request, string $key, int $item): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'index' => ['required', 'integer', 'min:0'],
        ]);

        $menu = Menu::query()->where('key', $key)->first();

        if (! $menu instanceof Menu) {
            throw new NotFoundHttpException;
        }

        $moved = $this->of($menu, $item);
        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        $parent = $parentId === null ? null : $this->of($menu, $parentId);

        // Into its own subtree: the tree would have no root, and the nested set would be a set
        // of two disconnected halves. Refused rather than corrected — nobody meant it.
        if (Placement::loops($moved, $parent)) {
            throw new NotFoundHttpException;
        }

        Placement::apply($menu, $moved, $parent, (int) $validated['index']);

        return ApiResponse::data(['id' => (int) $moved->refresh()->getKey()]);
    }

    private function of(Menu $menu, int $id): MenuItem
    {
        $item = MenuItem::query()->where('menu_id', $menu->getKey())->find($id);

        if (! $item instanceof MenuItem) {
            throw new NotFoundHttpException;
        }

        return $item;
    }
}
