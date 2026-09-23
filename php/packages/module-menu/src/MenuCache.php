<?php

declare(strict_types=1);

namespace WebxUi\Menu;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Localization\Locales;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * The built menus, kept per menu and per language.
 *
 * Forgotten by name rather than counted by version (§2, decision 14). The set of keys is known —
 * menus times languages — and a counter is the thing that looks cleaner and then misbehaves:
 * `increment` on a missing key does different things in different stores, and a counter that is
 * evicted comes back at zero, which brings records written under the old number back to life.
 *
 * Tags would have been the other obvious answer, and the `file` and `database` stores do not
 * have them at all — `file` being the default on exactly the small sites this is written for.
 *
 * What is kept is the parsed tree with no highlighting in it: which item is the current one
 * differs on every page, so it is worked out after the read. Next to the tree is `built_at`,
 * which is what the panel's reset button is labelled with.
 */
final class MenuCache
{
    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  Closure(): list<array<string, mixed>>  $build
     * @return list<array<string, mixed>>
     */
    public function get(string $key, string $locale, Closure $build): array
    {
        if (! $this->enabled()) {
            return $build();
        }

        $record = $this->cache->get($this->cacheKey($key, $locale));

        if (is_array($record) && is_array($record['tree'] ?? null)) {
            /** @var list<array<string, mixed>> $tree */
            $tree = $record['tree'];

            return $tree;
        }

        $tree = $build();

        $this->cache->put(
            $this->cacheKey($key, $locale),
            ['built_at' => Carbon::now()->toAtomString(), 'tree' => $tree],
            $this->ttl(),
        );

        return $tree;
    }

    /**
     * When this menu's cache was built, or null when none of it is.
     *
     * The oldest of its languages, because that is what the age of a cache means: the panel
     * says "built today at 08:10" about the whole menu, and an administrator thinking about a
     * menu is not thinking about the pair "this menu and Ukrainian".
     */
    public function builtAt(string $key): ?Carbon
    {
        $oldest = null;

        foreach ($this->localeCodes() as $locale) {
            $record = $this->cache->get($this->cacheKey($key, $locale));

            if (! is_array($record) || ! is_string($record['built_at'] ?? null)) {
                continue;
            }

            $built = Carbon::parse($record['built_at']);

            if ($oldest === null || $built->lessThan($oldest)) {
                $oldest = $built;
            }
        }

        return $oldest;
    }

    /** One menu, in every language. */
    public function forget(?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        foreach ($this->localeCodes() as $locale) {
            $this->cache->forget($this->cacheKey($key, $locale));
        }
    }

    /**
     * The menus an entity actually stands in, and no others.
     *
     * This is what `(entity_type, entity_id)` is indexed for: publishing a page forgets the
     * header it is in and leaves the footer alone.
     */
    public function forgetForEntity(string $type, int|string $id): void
    {
        $menuIds = MenuItem::query()
            ->where('entity_type', $type)
            ->where('entity_id', $id)
            ->distinct()
            ->pluck('menu_id');

        if ($menuIds->isEmpty()) {
            return;
        }

        foreach (Menu::query()->whereKey($menuIds)->pluck('key') as $key) {
            $this->forget(is_string($key) ? $key : null);
        }
    }

    /**
     * Every menu there is: what the configuration declares and what the table holds.
     *
     * The table is read behind a `try`, because this is reachable from a model event during a
     * migration — an address written while the site is being installed — and a menu cache that
     * has never been built is nothing to fail over.
     */
    public function flush(): void
    {
        $keys = array_keys((array) $this->config->get('webx-menu.menus', []));

        try {
            foreach (Menu::query()->pluck('key') as $key) {
                if (is_string($key)) {
                    $keys[] = $key;
                }
            }
        } catch (Throwable) {
            // No `menus` table yet.
        }

        foreach (array_unique($keys) as $key) {
            $this->forget((string) $key);
        }
    }

    public function enabled(): bool
    {
        return (bool) ($this->config->get('webx-menu.cache.enabled') ?? true);
    }

    public function ttl(): int
    {
        return (int) ($this->config->get('webx-menu.cache.ttl') ?? 3600);
    }

    public function cacheKey(string $key, string $locale): string
    {
        return "webx-menu:{$key}:{$locale}";
    }

    /**
     * Every language a record could have been written under: the ones the site publishes, plus
     * whichever one the application happens to be speaking — a request answered in a language
     * that has since been switched off still put a record somewhere.
     *
     * @return list<string>
     */
    private function localeCodes(): array
    {
        return array_values(array_unique([...$this->locales->codes(), $this->locales->current()]));
    }
}
