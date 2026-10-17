<?php

declare(strict_types=1);

namespace WebxUi\Menu;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use WebxUi\Cache\CacheGroups;
use WebxUi\Localization\Locales;

/**
 * The built menus, kept per menu and per language in the `menu` cache group
 * (WEBX_UI_CACHE.md).
 *
 * An entry is built from the menu's own rows and from the address registry, so its key carries
 * the versions of both groups: an item saved, dragged or deleted moves `menu`, a row of the
 * registry written — a page renamed last week, and every page under it — moves `routes`, and
 * either way the next read builds the tree again. What the registry cannot see — a page
 * published, a title changed without touching the slug — the subscriber moves `menu` for
 * ({@see CacheSubscriber}).
 *
 * One version for every menu: forgetting the header forgets the footer too. A tree is three
 * queries to build, and a counter per menu would be a counter the panel and the agent's tool
 * had to know the names of.
 *
 * What is kept is the parsed tree with no highlighting in it: which item is the current one
 * differs on every page, so it is worked out after the read. Next to the tree is `built_at`,
 * which is what the panel's reset button is labelled with.
 */
final class MenuCache
{
    public const GROUP = 'menu';

    public function __construct(
        private readonly CacheGroups $groups,
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

        $record = $this->groups->remember(
            $this->name($key, $locale),
            self::depends(),
            static fn (): array => ['built_at' => Carbon::now()->toAtomString(), 'tree' => $build()],
            $this->ttl(),
            localized: false,
        );

        /** @var list<array<string, mixed>> $tree */
        $tree = is_array($record) && is_array($record['tree'] ?? null) ? $record['tree'] : $build();

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
            $record = $this->groups->get($this->name($key, $locale), self::depends(), localized: false);

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

    /**
     * Forget the built menus. Asked about one menu — the panel's button names one — and
     * answered for all of them: the version is one number (see above).
     */
    public function forget(?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        $this->flush();
    }

    /**
     * Every menu there is: the `menu` group moves, and whatever was built on it is a miss.
     */
    public function flush(): void
    {
        $this->groups->bump(self::GROUP);
    }

    public function enabled(): bool
    {
        return (bool) ($this->config->get('webx-menu.cache.enabled') ?? true);
    }

    public function ttl(): int
    {
        return (int) ($this->config->get('webx-menu.cache.ttl') ?? 3600);
    }

    /**
     * The groups a built menu depends on: its own rows and the addresses its items lead to.
     *
     * @return list<string>
     */
    public static function depends(): array
    {
        return [self::GROUP, CacheGroups::ROUTES];
    }

    private function name(string $key, string $locale): string
    {
        return "menu:{$key}:{$locale}";
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
