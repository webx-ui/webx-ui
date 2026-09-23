<?php

declare(strict_types=1);

namespace WebxUi\Menu\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Menu\MenuCache;
use WebxUi\Menu\Menus;
use WebxUi\Menu\Models\Menu;

/**
 * What an agent reads before it arranges anything (§11).
 *
 * The catalogue and the house rules in one document: which menus this site has and which of them
 * a template asks for by name, which looks each one offers, what kinds of thing can be linked to
 * at all, and the handful of decisions that are easy to get wrong in a way nobody notices — that
 * an item should hang on an entity rather than on a path, that a path is written without its
 * language prefix, that an empty label is a feature.
 *
 * Rules rather than a tutorial, because an agent that read `menu_add_link`'s description already
 * knows how to call it; what it does not know is what this site has decided.
 */
final class MenuResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'menu://menus',
                'Menus',
                'The menus of this site and the house rules for them: which keys the templates ask for, which '
                .'looks each menu offers, what can be linked to, and what an item should point at. Read this '
                .'before changing a menu.',
                fn (): array => $this->catalogue(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogue(): array
    {
        /** @var Menus $menus */
        $menus = $this->container->make(Menus::class);
        /** @var MenuCache $cache */
        $cache = $this->container->make(MenuCache::class);
        /** @var Locales $locales */
        $locales = $this->container->make(Locales::class);
        /** @var LinkSources $sources */
        $sources = $this->container->make(LinkSources::class);

        $rows = Menu::query()->withCount('items')->get()->keyBy('key');
        $listed = [];

        foreach ($menus->keys() as $key) {
            $row = $rows->get($key);

            $listed[] = [
                'key' => $key,
                'title' => $row instanceof Menu ? $row->label() : ($menus->declaredTitle($key) ?? $key),
                'declared' => $menus->isDeclared($key),
                'items_count' => $row instanceof Menu ? (int) ($row->getAttribute('items_count') ?? 0) : 0,
                'variants' => $menus->variants($key),
                'cache' => [
                    'enabled' => $cache->enabled(),
                    'built_at' => $cache->builtAt($key)?->toAtomString(),
                ],
            ];
        }

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'menus' => $listed,
            'link_to' => array_map(static fn (LinkSource $source): array => [
                'entity_type' => $source->type(),
                'title' => $source->title(),
            ], $sources->all()),
            'rules' => $this->rules(),
        ];
    }

    /**
     * The decisions of this module, in the order somebody trips over them.
     *
     * @return list<string>
     */
    private function rules(): array
    {
        return [
            'Hang an item on an entity rather than on a path wherever there is an entity to hang it on. An '
            .'entity keeps its address: renaming or moving a page carries the menu item with it and leaves a '
            .'redirect behind. A path written by hand is a path that goes stale silently.',

            'A path on this site is written without its language prefix — "/account", not "/en/account". The '
            .'prefix is added when the item is printed, in the language being read. An address somewhere else '
            .'is written in full and is left alone.',

            'A menu is one tree for every language. Labels are translated; an item that has no label in the '
            .'language being read, and points at nothing named in it either, is left out of that language '
            .'rather than printed empty. Use locales for the other case — something the English footer has '
            .'and the Russian one does not.',

            'Leave the label out and the item is called what the entity behind it is called, in every '
            .'language at once. Write one only where the tree says "Work With Katia" and the header wants '
            .'something shorter.',

            'A group heading is is_heading, which is about how to draw it, and not target: none, which is '
            .'about where it goes. A heading with children and a page of its own is an ordinary thing.',

            'An item may point at something that is not on the site yet — a draft page is a legitimate '
            .'target, since a menu is usually built before the pages in it are published. Such an item is '
            .'marked available: false and the site leaves it out until the page is published.',

            'Menus are not created or deleted here. A declared menu is a template asking for that spelling, '
            .'so its key cannot change and it cannot be removed; a menu of somebody\'s own is made in the '
            .'panel for a template that is being written.',

            'variant is the look this site\'s markup divides by, and only the values a menu declares are '
            .'accepted. A value that is not on its list would be an item that quietly renders as any other.',
        ];
    }
}
