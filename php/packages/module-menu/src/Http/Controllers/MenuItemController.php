<?php

declare(strict_types=1);

namespace WebxUi\Menu\Http\Controllers;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Localization\Locales;
use WebxUi\Menu\Http\Requests\MenuItemRequest;
use WebxUi\Menu\Http\Resources\MenuItemResource;
use WebxUi\Menu\Menus;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * The tree of one menu, and the items in it.
 *
 * The whole tree at once (§10): menus are small — a header is seven items and a footer twenty —
 * and a level at a time would cost a request per fold for a screen that is opened to see the
 * shape of the thing. What is not small is the number of entities the items point at, and that
 * is where the batching is.
 */
final class MenuItemController
{
    public function __construct(
        private readonly Menus $menus,
        private readonly LinkUrls $urls,
        private readonly Locales $locales,
    ) {}

    public function index(Request $request, string $key): JsonResponse
    {
        $menu = Menu::query()->where('key', $this->known($key))->first();

        // A declared menu with no row yet is an empty tree rather than a 404: the section shows
        // it from the first day, and the first item saved into it is what makes the row.
        $items = $menu instanceof Menu ? $this->items($menu) : new EloquentCollection;

        return ApiResponse::data(array_map(
            static fn (MenuItemResource $item): array => $item->toArray(),
            MenuItemResource::tree($items, $this->urls, $this->locale($request)),
        ));
    }

    /**
     * A new item, at the end of the level it was asked for.
     *
     * At the end and not where a form said, because where an item sits is the tree's business
     * and there is one gesture for it — dragging. A dialog that also offered a position would be
     * a second way of saying the same thing, and the two would disagree.
     */
    public function store(MenuItemRequest $request, string $key): JsonResponse
    {
        $menu = $this->menus->ensure($this->known($key));

        $item = new MenuItem(['menu_id' => $menu->getKey()]);
        $this->fill($item, $request);

        $parent = $this->parent($menu, $request->parentId());

        if ($parent instanceof MenuItem) {
            $item->appendTo($parent);
        } else {
            $item->saveAsRoot();
        }

        return ApiResponse::data($this->one($item->refresh(), $request), 201);
    }

    public function update(MenuItemRequest $request, string $key, int $item): JsonResponse
    {
        $found = $this->item($this->known($key), $item);

        $this->fill($found, $request);
        $found->save();

        return ApiResponse::data($this->one($found->refresh(), $request));
    }

    /**
     * An item and everything under it.
     *
     * How many went is in the answer because the panel says it out loud before asking, and it
     * has to be the same question the delete itself answers — an item read before its children
     * were added still holds the bounds it had then, and arithmetic over those would promise
     * one number and take another (§10).
     */
    public function destroy(string $key, int $item): JsonResponse
    {
        $found = $this->item($this->known($key), $item);

        $count = $found->descendants()->count() + 1;

        $found->delete();

        return ApiResponse::data(['deleted' => $count]);
    }

    /**
     * @return EloquentCollection<int, MenuItem>
     */
    private function items(Menu $menu): EloquentCollection
    {
        /** @var EloquentCollection<int, MenuItem> $items */
        $items = MenuItem::query()->where('menu_id', $menu->getKey())->ordered()->get();

        return $items;
    }

    private function fill(MenuItem $item, MenuItemRequest $request): void
    {
        $item->fillLink($request->link());

        // Only when it was sent. An empty map is a label nobody wrote, and a request that left
        // the field out entirely would otherwise clear the labels of nine other languages.
        if ($request->has('title')) {
            $item->setTranslations('title', $request->titles());
        }

        if ($request->has('variant')) {
            $item->variant = (string) $request->input('variant');
        }

        if ($request->has('is_heading')) {
            $item->is_heading = $request->boolean('is_heading');
        }

        if ($request->has('visible')) {
            $item->visible = $request->boolean('visible');
        }

        if ($request->has('locales')) {
            $locales = $request->locales();

            // Null and not `[]`: "every language" is what an empty choice means, and two
            // spellings of it in the column would be two things to check on every read.
            $item->locales = $locales === [] ? null : $locales;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function one(MenuItem $item, Request $request): array
    {
        $locale = $this->locale($request);
        $link = $item->link();

        return (new MenuItemResource(
            $item,
            $this->urls,
            $locale,
            $this->urls->candidates([$link], $locale),
        ))->toArray();
    }

    private function parent(Menu $menu, ?int $parentId): ?MenuItem
    {
        if ($parentId === null) {
            return null;
        }

        $parent = MenuItem::query()->where('menu_id', $menu->getKey())->find($parentId);

        if (! $parent instanceof MenuItem) {
            throw new NotFoundHttpException;
        }

        return $parent;
    }

    /**
     * One item of this menu, and only of this one.
     *
     * Through the menu rather than by id alone: the id is in the address beside a key, and an
     * id that belongs to another menu's tree has to be a 404 and not a move across menus that
     * nobody asked for.
     */
    private function item(string $key, int $id): MenuItem
    {
        $menu = Menu::query()->where('key', $key)->first();

        $item = $menu instanceof Menu
            ? MenuItem::query()->where('menu_id', $menu->getKey())->find($id)
            : null;

        if (! $item instanceof MenuItem) {
            throw new NotFoundHttpException;
        }

        return $item;
    }

    private function known(string $key): string
    {
        if (! in_array($key, $this->menus->keys(), true)) {
            throw new NotFoundHttpException;
        }

        return $key;
    }

    /** The content language the panel is reading in, which is what a label is drawn from. */
    private function locale(Request $request): string
    {
        $asked = $request->query('locale');

        return is_string($asked) && $asked !== '' ? $asked : $this->locales->current();
    }
}
