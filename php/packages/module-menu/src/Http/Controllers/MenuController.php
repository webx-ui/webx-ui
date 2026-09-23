<?php

declare(strict_types=1);

namespace WebxUi\Menu\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;
use WebxUi\Menu\Http\Requests\MenuRequest;
use WebxUi\Menu\Http\Resources\MenuResource;
use WebxUi\Menu\MenuCache;
use WebxUi\Menu\Menus;
use WebxUi\Menu\Models\Menu;

/**
 * The menus of the site: what the configuration asks for, and what somebody made.
 *
 * The list is the union of the two (§5), which is why it is assembled here rather than selected:
 * a declared menu is on the screen before it has a row, and its row appears the first time it is
 * saved. Everything below addresses a menu by its key for the same reason — for part of its life
 * a menu has no id.
 */
final class MenuController
{
    public function __construct(
        private readonly Menus $menus,
        private readonly MenuCache $cache,
        private readonly Locales $locales,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rows = Menu::query()->withCount('items')->get()->keyBy('key');
        $manage = $this->canManage($request);

        $menus = [];

        foreach ($this->menus->keys() as $key) {
            $row = $rows->get($key);

            $menus[] = (new MenuResource(
                $key,
                $row instanceof Menu ? $row : null,
                $row instanceof Menu ? (int) ($row->getAttribute('items_count') ?? 0) : 0,
                $this->menus,
                $this->cache,
                $manage,
            ))->toArray();
        }

        return ApiResponse::data($menus);
    }

    /**
     * A menu of somebody's own: a key and a name.
     *
     * Made straight away rather than lazily, unlike a declared one. There is nothing to wait
     * for — nobody asked for this key in a template, so the row is the only thing that says it
     * exists at all.
     */
    public function store(MenuRequest $request): JsonResponse
    {
        $menu = new Menu(['key' => $request->key()]);
        $menu->setTranslation('title', $this->locales->current(), $request->title());
        $menu->save();

        return ApiResponse::data($this->one($menu->key, $menu, $request), 201);
    }

    /**
     * The name, and for a menu of somebody's own the key as well.
     *
     * This is where a declared menu's row first appears: `ensure()` makes it with the name the
     * configuration gave it, and what was sent is written over that.
     */
    public function update(MenuRequest $request, string $key): JsonResponse
    {
        $menu = $this->menus->ensure($this->known($key));

        $menu->setTranslation('title', $this->locales->current(), $request->title());

        $wanted = $request->input('key');

        // Only when it was sent and only when it differs: the model refuses to rename a declared
        // menu, and a request that merely echoed the key back would be refused for doing nothing.
        if (is_string($wanted) && $wanted !== '' && $wanted !== $menu->key) {
            $menu->key = $request->key();
        }

        $menu->save();

        return ApiResponse::data($this->one($menu->key, $menu->refresh(), $request));
    }

    /**
     * A menu of somebody's own, with its items.
     *
     * The items go by the cascade on `menu_id` rather than one by one: a delete here is not a
     * bin, and half a tree left behind by a failed loop would be worse than either outcome. A
     * declared menu is refused by the model, which is where the rule belongs — an agent and an
     * import come through the same door.
     */
    public function destroy(string $key): JsonResponse
    {
        $menu = Menu::query()->where('key', $this->known($key))->first();

        if (! $menu instanceof Menu) {
            throw new NotFoundHttpException;
        }

        $menu->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function one(string $key, ?Menu $menu, Request $request): array
    {
        return (new MenuResource(
            $key,
            $menu,
            $menu instanceof Menu ? (int) $menu->items()->count() : 0,
            $this->menus,
            $this->cache,
            $this->canManage($request),
        ))->toArray();
    }

    /** A key that is either declared or in the table; anything else is a menu that is not here. */
    private function known(string $key): string
    {
        if (! in_array($key, $this->menus->keys(), true)) {
            throw new NotFoundHttpException;
        }

        return $key;
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof HasPermissions && $user->hasPermission('menu.manage');
    }
}
