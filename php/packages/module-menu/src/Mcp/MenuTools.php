<?php

declare(strict_types=1);

namespace WebxUi\Menu\Mcp;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Menu\Exceptions\MenuException;
use WebxUi\Menu\Http\Resources\MenuItemResource;
use WebxUi\Menu\MenuCache;
use WebxUi\Menu\Menus;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;
use WebxUi\Menu\Panel\ItemInput;
use WebxUi\Menu\Tree\Placement;

/**
 * What an agent can do with the menus of a site (§11).
 *
 * The same doors the panel uses: {@see ItemInput} decides what an item may be, {@see Link}
 * normalises where it points, {@see Placement} works out what a position among siblings means,
 * and the cache is forgotten by the model events a save raises rather than by a line here. An
 * item an agent wrote is an item the panel would have accepted.
 *
 * The names are longer than they need to be, and deliberately. A real client shows a tool by the
 * part of its name after the module prefix, so `menu_list` and `menu_get` would stand in the
 * connector's settings as "List" and "Get" beside everybody else's (§4 of the brief).
 *
 * One thing is missing on purpose: menus are not made here. The declared ones are the site's
 * templates asking for them by name, and a menu of somebody's own is made for a template or a
 * block that a person is writing — there is nothing in either for an agent to decide.
 */
final class MenuTools
{
    /** Past the size of any menu somebody would arrange by hand, and a stop for the one that is not. */
    private const ITEM_LIMIT = 500;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $menu = [
            'type' => 'string',
            'description' => 'The key of the menu — "header", as menu_list_menus reports it.',
        ];
        $item = [
            'type' => 'integer',
            'description' => 'The id of the item, as menu_get_tree reports it.',
        ];
        $locale = [
            'type' => 'string',
            'description' => 'A language code; the site\'s default when omitted. Labels always answer with every language.',
        ];

        return [
            Tool::read(
                'list_menus',
                'Every menu this site has: the key a template asks for it by, what it is called, how many items '
                .'are in it, the looks an item in it may take, and whether its cache is built. Read this first — '
                .'everything else here names a menu by that key, and a key nobody declared is a menu no template '
                .'prints.',
                fn (): array => $this->listMenus(),
            ),

            Tool::read(
                'get_tree',
                'One menu as it is arranged: every item nested the way it is nested, with the label it carries in '
                .'each language, what it points at, the address the site would print for it right now, and whether '
                .'that address leads anywhere a visitor can go. An item marked available: false points at something '
                .'that is not on the site — a draft, usually — and the site leaves it out while the panel still '
                .'shows it.',
                fn (array $arguments): array => $this->getTree($arguments),
                ['properties' => ['menu' => $menu, 'locale' => $locale], 'required' => ['menu']],
            ),

            Tool::mutating(
                'add_link',
                'Put an item into a menu: what it points at, what it is called, and where it goes. A target is one '
                .'of three and you say which — an entity of this site by its kind and id, an address of your own, '
                .'or nothing at all, which is what a group heading is. Leave the label out and the item is called '
                .'whatever the entity behind it is called, in every language at once, which is usually what you '
                .'want. It goes at the end of its level unless you name a position.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->addLink($arguments)),
                ['properties' => [
                    'menu' => $menu,
                    'target' => ['type' => 'string', 'enum' => ['entity', 'url', 'none'], 'description' => 'entity — a record of this site · url — an address · none — a heading or a separator.'],
                    'entity_type' => ['type' => 'string', 'description' => 'The kind of record, as menu://menus lists them: "page", "article".'],
                    'entity_id' => ['type' => 'integer', 'description' => 'Its id, from the tools of the module that owns it.'],
                    'url' => ['type' => 'string', 'description' => 'A path on this site — "/account" — or a full address somewhere else. A path gets the language prefix when it is printed; write it without one.'],
                    'hash' => ['type' => 'string', 'description' => 'An anchor on the page, without its #.'],
                    'title' => ['type' => ['string', 'object'], 'description' => 'The label: one language as a string, or every language as { "en": "…", "ru": "…" }. Omit it to be called what the entity is called.'],
                    'parent' => ['type' => ['integer', 'null'], 'description' => 'The item this one goes under; a top-level item when omitted.'],
                    'index' => ['type' => 'integer', 'description' => 'Where in that level, counting from 0. At the end when omitted.'],
                    'variant' => ['type' => 'string', 'description' => 'One of the looks this menu offers, as menu_list_menus reports them. "link" when omitted.'],
                    'is_heading' => ['type' => 'boolean', 'description' => 'Draw it as the heading of the group under it rather than as a link. Separate from where it points: a heading may still have a page of its own.'],
                    'new_tab' => ['type' => 'boolean', 'description' => 'Open in a new tab. noopener noreferrer is added by whoever renders it; that is not a choice.'],
                    'rel' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => Link::REL], 'description' => 'Any of nofollow, sponsored, ugc.'],
                    'locales' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Show it only in these languages. Empty means every one, which is the ordinary case.'],
                    'visible' => ['type' => 'boolean', 'description' => 'A hidden item stays in the panel and takes its children out of the site with it.'],
                ], 'required' => ['menu', 'target']],
            ),

            Tool::mutating(
                'update_link',
                'Change an item: what it points at, its label, its look, its flags. A key you leave out keeps what '
                .'it had, so send only what changes — and a label sent as { "en": "…" } replaces the labels of '
                .'every language, not only English. Where an item sits is not changed here: that is menu_move_link.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->updateLink($arguments)),
                ['properties' => [
                    'menu' => $menu,
                    'item' => $item,
                    'target' => ['type' => 'string', 'enum' => ['entity', 'url', 'none'], 'description' => 'Changing this changes what the item points at; send the target and the fields that go with it together.'],
                    'entity_type' => ['type' => 'string'],
                    'entity_id' => ['type' => 'integer'],
                    'url' => ['type' => 'string'],
                    'hash' => ['type' => 'string'],
                    'title' => ['type' => ['string', 'object'], 'description' => 'The label in one language or in all of them; an empty string clears it in that language.'],
                    'variant' => ['type' => 'string'],
                    'is_heading' => ['type' => 'boolean'],
                    'new_tab' => ['type' => 'boolean'],
                    'rel' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => Link::REL]],
                    'locales' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'visible' => ['type' => 'boolean'],
                ], 'required' => ['menu', 'item']],
            ),

            Tool::mutating(
                'move_link',
                'Put an item somewhere else in the same menu: under another item, or at the top level, and how far '
                .'down that level. It takes whatever is under it with it. An item cannot be moved inside its own '
                .'branch, and an index past the end of a level simply means the end of it.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->moveLink($arguments)),
                ['properties' => [
                    'menu' => $menu,
                    'item' => $item,
                    'parent' => ['type' => ['integer', 'null'], 'description' => 'The item it goes under; null or omitted puts it at the top level.'],
                    'index' => ['type' => 'integer', 'description' => 'Where in that level, counting from 0.'],
                ], 'required' => ['menu', 'item', 'index']],
            ),

            Tool::mutating(
                'remove_link',
                'Take an item out of a menu, together with everything under it — the answer says how many items '
                .'that was. A heading with four links under it is five. Nothing else is touched: the pages an item '
                .'pointed at are pages, and removing the link to one does not remove it.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->removeLink($arguments)),
                ['properties' => ['menu' => $menu, 'item' => $item], 'required' => ['menu', 'item']],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listMenus(): array
    {
        $menus = $this->menus();
        $cache = $this->cache();
        $rows = Menu::query()->withCount('items')->get()->keyBy('key');
        $listed = [];

        foreach ($menus->keys() as $key) {
            $row = $rows->get($key);

            $listed[] = [
                'key' => $key,
                'title' => $row instanceof Menu ? $row->label() : ($menus->declaredTitle($key) ?? $key),
                // A template asks for this one by name: it cannot be renamed or deleted, and
                // it is on the screen before anybody has saved it.
                'declared' => $menus->isDeclared($key),
                'items_count' => $row instanceof Menu ? (int) ($row->getAttribute('items_count') ?? 0) : 0,
                'variants' => $menus->variants($key),
                'cache' => [
                    'enabled' => $cache->enabled(),
                    'built_at' => $cache->builtAt($key)?->toAtomString(),
                ],
            ];
        }

        return ['count' => count($listed), 'menus' => $listed];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getTree(array $arguments): array
    {
        $key = $this->key($arguments);
        $locale = $this->locale($arguments);
        $items = $this->items($key);

        return [
            'menu' => $key,
            'locale' => $locale,
            'locales' => $this->locales()->codes(),
            'variants' => $this->menus()->variants($key),
            'count' => $items->count(),
            'items' => array_map(
                static fn (MenuItemResource $item): array => $item->toArray(),
                MenuItemResource::tree($items, $this->urls(), $locale),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function addLink(array $arguments): array
    {
        $key = $this->key($arguments);
        $input = $this->validated($key, $arguments, new MenuItem(['target' => 'none']));
        $link = Link::fromArray($input['link']);
        $parent = $this->parent($key, $arguments);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_add' => $this->describe($link, $input),
                'to' => $key,
                'under' => $parent?->getKey(),
            ];
        }

        $menu = $this->menus()->ensure($key);

        $item = new MenuItem(['menu_id' => $menu->getKey()]);
        $this->fill($item, $input, $arguments);

        if ($parent instanceof MenuItem) {
            $item->appendTo($parent);
        } else {
            $item->saveAsRoot();
        }

        if (isset($arguments['index'])) {
            Placement::apply($menu, $item->refresh(), $parent, $this->index($arguments));
        }

        return ['item' => $this->one($item->refresh(), $this->locale($arguments))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateLink(array $arguments): array
    {
        $key = $this->key($arguments);
        $found = $this->item($key, $arguments);
        $input = $this->validated($key, $arguments, $found);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_change' => array_values(array_intersect(
                    ['target', 'entity_type', 'entity_id', 'url', 'hash', 'title', 'variant', 'is_heading', 'new_tab', 'rel', 'locales', 'visible'],
                    array_keys($arguments),
                )),
                'item' => (int) $found->getKey(),
            ];
        }

        $this->fill($found, $input, $arguments);
        $found->save();

        return ['item' => $this->one($found->refresh(), $this->locale($arguments))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function moveLink(array $arguments): array
    {
        $key = $this->key($arguments);
        $moved = $this->item($key, $arguments);
        $parent = $this->parent($key, $arguments);
        $index = $this->index($arguments);

        if (Placement::loops($moved, $parent)) {
            throw new ToolFailure('An item cannot be moved inside its own branch: the item you named as the parent is that item, or is under it.');
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_move' => (int) $moved->getKey(),
                'under' => $parent?->getKey(),
                'index' => $index,
                'items_moving' => $moved->descendants()->count() + 1,
            ];
        }

        Placement::apply($this->menu($key), $moved, $parent, $index);

        return ['item' => $this->one($moved->refresh(), $this->locale($arguments))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function removeLink(array $arguments): array
    {
        $key = $this->key($arguments);
        $found = $this->item($key, $arguments);

        // Counted with the same question the delete answers. Arithmetic over the bounds would
        // promise one number and take another whenever the item was read before its children
        // were added (§10).
        $count = $found->descendants()->count() + 1;

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_remove' => $count, 'item' => (int) $found->getKey()];
        }

        $found->delete();

        return ['removed' => $count, 'menu' => $key];
    }

    /**
     * What arrived, checked by the rules the dialog is checked by.
     *
     * The link travels flat in a tool call and as one object in the panel, so it is put back
     * together here — and put together out of what the item already holds, so that a call
     * naming only `visible` is not refused for a target nobody was changing.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{link: array<string, mixed>, title: array<string, string>|null, locales: list<string>|null}
     */
    private function validated(string $key, array $arguments, MenuItem $item): array
    {
        $link = $this->linkFrom($arguments, $item);
        $input = ['link' => $link];

        if (array_key_exists('title', $arguments)) {
            $input['title'] = $this->titles($arguments['title']);
        }

        foreach (['variant', 'is_heading', 'visible'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $input[$field] = $arguments[$field];
            }
        }

        if (array_key_exists('locales', $arguments)) {
            $input['locales'] = is_array($arguments['locales']) ? array_values(array_map(strval(...), $arguments['locales'])) : [];
        }

        $this->validator()->make($input, ItemInput::rules($key))->validate();

        return [
            'link' => $link,
            'title' => isset($input['title']) && is_array($input['title']) ? $input['title'] : null,
            'locales' => isset($input['locales']) && is_array($input['locales']) ? $input['locales'] : null,
        ];
    }

    /**
     * Where the item points: what it points at now, with what arrived written over it.
     *
     * A call naming only `visible` would otherwise reach the rules with no target at all and be
     * refused for something nobody was changing. A call that does name a target starts from
     * nothing instead — an item moved from a page to an address would otherwise keep a morph
     * pair that nothing reads afterwards and everything has to step over.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function linkFrom(array $arguments, MenuItem $item): array
    {
        $link = array_key_exists('target', $arguments)
            ? ['target' => $arguments['target'], 'new_tab' => $item->new_tab, 'rel' => $item->rel ?? []]
            : $item->link()->toArray();

        foreach (['entity_type', 'entity_id', 'url', 'hash', 'new_tab', 'rel'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $link[$field] = $arguments[$field];
            }
        }

        return $link;
    }

    /**
     * @param  array{link: array<string, mixed>, title: array<string, string>|null, locales: list<string>|null}  $input
     * @param  array<string, mixed>  $arguments
     */
    private function fill(MenuItem $item, array $input, array $arguments): void
    {
        $item->fillLink(Link::fromArray($input['link']));

        if ($input['title'] !== null) {
            $item->setTranslations('title', $input['title']);
        }

        foreach (['variant' => 'string', 'is_heading' => 'bool', 'visible' => 'bool'] as $field => $kind) {
            if (! array_key_exists($field, $arguments)) {
                continue;
            }

            $item->setAttribute($field, $kind === 'bool' ? (bool) $arguments[$field] : (string) $arguments[$field]);
        }

        if ($input['locales'] !== null) {
            // Null and not `[]`: "every language" is what an empty choice means, and two
            // spellings of it in the column would be two things to check on every read.
            $item->locales = $input['locales'] === [] ? null : $input['locales'];
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function describe(Link $link, array $input): string
    {
        $where = match ($link->target->value) {
            'entity' => $link->entityType.' #'.$link->entityId,
            'url' => (string) ($link->url ?? $link->fragment()),
            default => 'nowhere',
        };

        $label = $input['title'] === null ? null : reset($input['title']);

        return is_string($label) && $label !== '' ? "{$label} → {$where}" : $where;
    }

    /**
     * @return array<string, mixed>
     */
    private function one(MenuItem $item, string $locale): array
    {
        $urls = $this->urls();

        return (new MenuItemResource($item, $urls, $locale, $urls->candidates([$item->link()], $locale)))->toArray();
    }

    /**
     * @return EloquentCollection<int, MenuItem>
     */
    private function items(string $key): EloquentCollection
    {
        $menu = Menu::query()->where('key', $key)->first();

        if (! $menu instanceof Menu) {
            // A declared menu nobody has saved yet is an empty tree and not a refusal: it is
            // on the panel's screen from the first day, and so is it here.
            return new EloquentCollection;
        }

        /** @var EloquentCollection<int, MenuItem> $items */
        $items = MenuItem::query()->where('menu_id', $menu->getKey())->ordered()->limit(self::ITEM_LIMIT)->get();

        return $items;
    }

    /**
     * Turn a refusal into something the agent can read.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (MenuException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function key(array $arguments): string
    {
        $key = $arguments['menu'] ?? null;

        if (! is_string($key) || trim($key) === '') {
            throw new ToolFailure('`menu` is required: the key of a menu, as menu_list_menus reports it.');
        }

        $key = trim($key);

        if (! in_array($key, $this->menus()->keys(), true)) {
            throw new ToolFailure(
                "This site has no menu [{$key}]. menu_list_menus says which it has; menus are not made here — a "
                .'declared one is a template asking for it by name, and one of somebody\'s own is made in the panel.'
            );
        }

        return $key;
    }

    private function menu(string $key): Menu
    {
        return $this->menus()->ensure($key);
    }

    /**
     * One item of this menu, and only of this one: an id belonging to another menu's tree has
     * to be a refusal and not a move across menus nobody asked for.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function item(string $key, array $arguments): MenuItem
    {
        $id = $arguments['item'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new ToolFailure('`item` is required: the id of an item, as menu_get_tree reports it.');
        }

        $found = $this->find($key, (int) $id);

        if (! $found instanceof MenuItem) {
            throw new ToolFailure("Menu [{$key}] has no item [{$id}]. menu_get_tree says what is in it.");
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function parent(string $key, array $arguments): ?MenuItem
    {
        $id = $arguments['parent'] ?? null;

        if ($id === null || $id === '') {
            return null;
        }

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new ToolFailure('`parent` is the id of another item of the same menu, or null for the top level.');
        }

        $parent = $this->find($key, (int) $id);

        if (! $parent instanceof MenuItem) {
            throw new ToolFailure("Menu [{$key}] has no item [{$id}] to put this under.");
        }

        return $parent;
    }

    private function find(string $key, int $id): ?MenuItem
    {
        $menu = Menu::query()->where('key', $key)->first();

        return $menu instanceof Menu
            ? MenuItem::query()->where('menu_id', $menu->getKey())->find($id)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function index(array $arguments): int
    {
        $index = $arguments['index'] ?? null;

        if ($index === null) {
            return PHP_INT_MAX;
        }

        if (! is_int($index) && ! (is_string($index) && ctype_digit($index))) {
            throw new ToolFailure('`index` is a whole number counting from 0.');
        }

        return max(0, (int) $index);
    }

    /**
     * A label as it is stored: a map of languages, whichever of the two shapes arrived.
     *
     * @return array<string, string>
     */
    private function titles(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value) === '' ? [] : [$this->locales()->defaultCode() => trim($value)];
        }

        if (! is_array($value)) {
            throw new ToolFailure('`title` is a string, or an object of language code → label.');
        }

        $titles = [];

        foreach ($value as $locale => $label) {
            // A language written and then cleared is a language never written; keeping `""`
            // would make an empty string a label, and an empty label is a gap in a header.
            if (is_string($label) && trim($label) !== '') {
                $titles[(string) $locale] = trim($label);
            }
        }

        return $titles;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function locale(array $arguments): string
    {
        $asked = $arguments['locale'] ?? null;

        return is_string($asked) && $asked !== '' ? $asked : $this->locales()->defaultCode();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function menus(): Menus
    {
        return $this->container->make(Menus::class);
    }

    private function cache(): MenuCache
    {
        return $this->container->make(MenuCache::class);
    }

    private function urls(): LinkUrls
    {
        return $this->container->make(LinkUrls::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }

    private function validator(): ValidationFactory
    {
        return $this->container->make(ValidationFactory::class);
    }
}
