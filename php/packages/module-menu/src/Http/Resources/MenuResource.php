<?php

declare(strict_types=1);

namespace WebxUi\Menu\Http\Resources;

use WebxUi\Menu\MenuCache;
use WebxUi\Menu\Menus;
use WebxUi\Menu\Models\Menu;

/**
 * One menu as the left-hand list draws it.
 *
 * Not a `JsonResource`, because half the menus on this screen have no row to wrap: a declared
 * menu is visible from the first day and its row appears the first time somebody saves it (§5).
 * A resource over `null` would be a resource that has to check for its own model everywhere,
 * which is a longer way of writing this class.
 *
 * `cache` is the whole of what the reset button is labelled with: whether the cache is on at
 * all, and when the oldest of this menu's languages was built. Read from the record itself —
 * a `built_at` kept beside it in a table would be a second truth, and the two would part company
 * the moment a record was evicted (§10).
 */
final readonly class MenuResource
{
    public function __construct(
        private string $key,
        private ?Menu $menu,
        private int $itemsCount,
        private Menus $menus,
        private MenuCache $cache,
        private bool $canManage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $declared = $this->menus->isDeclared($this->key);

        return [
            // Null until the row exists. The panel addresses a menu by its key throughout, so
            // this is only ever read as "has anybody saved this one yet".
            'id' => $this->menu?->getKey(),
            'key' => $this->key,
            'title' => $this->title(),
            'declared' => $declared,
            'items_count' => $this->itemsCount,
            'variants' => $this->menus->variants($this->key),
            'cache' => [
                'enabled' => $this->cache->enabled(),
                'built_at' => $this->cache->builtAt($this->key)?->toAtomString(),
            ],
            'can' => [
                // A declared menu keeps its key because a template names it by that spelling,
                // and keeps existing for the same reason. Its title is an ordinary field.
                'rename' => $this->canManage && ! $declared,
                'delete' => $this->canManage && ! $declared,
            ],
        ];
    }

    /**
     * What to call it: the name somebody saved, else the one the configuration gave it, else
     * the key — which is at least what the template asks for.
     */
    private function title(): string
    {
        if ($this->menu instanceof Menu) {
            return $this->menu->label();
        }

        return $this->menus->declaredTitle($this->key) ?? $this->key;
    }
}
