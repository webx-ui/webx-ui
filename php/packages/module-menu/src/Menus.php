<?php

declare(strict_types=1);

namespace WebxUi\Menu;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\UniqueConstraintViolationException;
use WebxUi\Localization\Locales;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Rendering\Builder;
use WebxUi\Menu\Rendering\MenuTree;
use WebxUi\Menu\Rendering\SitePath;

/**
 * The menus of this site: the ones the configuration declares, the ones somebody made in the
 * panel, and the trees a template reads.
 *
 * A declared menu is one the templates ask for by name. It is visible in the panel from the
 * first day, empty, and its row appears the first time it is saved — no write on boot and no
 * synchronise command to forget to run. That is the whole of {@see ensure()}.
 */
final class Menus
{
    /**
     * How many times a write that lost a race is run again before the loser is told.
     *
     * With the menu held a race is rare, but a deadlock on the very first item of a menu — two
     * writers creating its row at once — is still one, and the second run of it simply waits.
     */
    private const ATTEMPTS = 3;

    public function __construct(
        private readonly Config $config,
        private readonly Locales $locales,
        private readonly Builder $builder,
        private readonly MenuCache $cache,
    ) {}

    /**
     * What `menu('header')` answers.
     *
     * A menu nobody has made yet is an empty collection rather than an exception: a header with
     * no items is a page that still works, and a missing menu is the kind of mistake found on a
     * staging site rather than by a visitor.
     */
    public function tree(string $key, ?string $locale = null): MenuTree
    {
        $locale ??= $this->locales->current();

        $nodes = $this->cache->get($key, $locale, fn (): array => $this->builder->build($key, $locale));

        return MenuTree::hydrate($nodes, SitePath::current());
    }

    /**
     * The menus named in `webx-menu.menus`, as they are written there.
     *
     * @return array<string, array<string, mixed>>
     */
    public function declared(): array
    {
        $declared = [];

        /** @var array<string, mixed> $menus */
        $menus = (array) $this->config->get('webx-menu.menus', []);

        foreach ($menus as $key => $entry) {
            $declared[(string) $key] = is_array($entry) ? $entry : ['title' => (string) $entry];
        }

        return $declared;
    }

    public function isDeclared(string $key): bool
    {
        return array_key_exists($key, $this->declared());
    }

    /** The name the configuration gives a menu, until a row of its own has one. */
    public function declaredTitle(string $key): ?string
    {
        $title = $this->declared()[$key]['title'] ?? null;

        return is_string($title) && trim($title) !== '' ? $title : null;
    }

    /**
     * The looks this menu offers, for the site's own markup to divide by.
     *
     * A menu that names none gets the fallback list, and `link` is always in it: an item has to
     * be able to be an ordinary link whatever else the site invented.
     *
     * @return list<string>
     */
    public function variants(string $key): array
    {
        $declared = $this->declared()[$key]['variants'] ?? null;

        if (! is_array($declared) || $declared === []) {
            $declared = (array) $this->config->get('webx-menu.variants', ['link']);
        }

        $variants = array_values(array_unique(array_map(strval(...), $declared)));

        return in_array('link', $variants, true) ? $variants : ['link', ...$variants];
    }

    /**
     * Every menu key there is, the declared ones first and in the order they are written.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_keys($this->declared());

        foreach (Menu::query()->orderBy('key')->pluck('key') as $key) {
            if (is_string($key) && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * The row behind a key, made now if this is the first time anybody needed one.
     *
     * Made with the name the configuration gave it, so that the first save of a declared menu
     * does not silently rename it to nothing.
     */
    public function ensure(string $key): Menu
    {
        $menu = Menu::query()->where('key', $key)->first();

        if ($menu instanceof Menu) {
            return $menu;
        }

        $menu = new Menu(['key' => $key]);
        $title = $this->declaredTitle($key);

        if ($title !== null) {
            // Under every language the panel is drawn in, and not the content languages: this
            // name is never printed on the site. The configuration writes one string, so every
            // one of them starts out the same, which is what an administrator sees before
            // deciding whether it is worth translating.
            foreach ($this->locales->panel() as $locale) {
                $menu->setTranslation('title', $locale['code'], $title);
            }
        }

        try {
            $menu->save();
        } catch (UniqueConstraintViolationException) {
            // Two first items of one declared menu at one moment: the other request made the
            // row a moment ago, and it is the same menu either of them meant.
            return Menu::query()->where('key', $key)->firstOrFail();
        }

        return $menu;
    }

    /**
     * Change a menu's tree with nobody else changing it meanwhile.
     *
     * Every write to a tree rewrites the bounds of everything to its right, and two of them in
     * one menu at one moment — an agent sends its calls side by side — either deadlock or leave
     * bounds that no longer add up. So the menu's row is held for the length of the write and
     * the writers line up behind it. The row is taken first, before anything is read: a read
     * made earlier in the transaction would show the tree from before the wait.
     *
     * A write that still loses a race is run again, whole, a couple of times; that is why
     * everything it reads — the item, the parent, the siblings — is read inside $work and not
     * handed in from outside.
     *
     * @template TResult
     *
     * @param  Closure(Menu): TResult  $work
     * @return TResult
     */
    public function writing(string $key, Closure $work): mixed
    {
        $menu = $this->ensure($key);

        return $menu->getConnection()->transaction(static function () use ($menu, $work): mixed {
            Menu::query()->whereKey($menu->getKey())->lockForUpdate()->first();

            return $work($menu);
        }, self::ATTEMPTS);
    }
}
