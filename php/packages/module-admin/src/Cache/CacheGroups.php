<?php

declare(strict_types=1);

namespace WebxUi\Admin\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\TransactionRolledBack;
use InvalidArgumentException;

/**
 * Cache on versioned keys (WEBX_UI_CACHE.md).
 *
 * Every group of data — the menu, the settings, the addresses — has a version, a counter in the
 * cache under `webx:ver:<group>`. A cached entry carries the versions of the groups it was built
 * from in its key, so moving a version is forgetting every entry built on the old one at once,
 * on any store: `file` and `database` have no tags, and a site on either must not be the one
 * that shows yesterday's menu.
 *
 * A group's version moves when one of its models is saved, deleted, restored or moved
 * (`bumpOn()`), or when whoever wrote without Eloquent says so (`bump()`). Inside a transaction
 * the move waits for the commit — a reader between the write and the commit would otherwise put
 * the old rows back under the new version — and nothing is cached while one is open, so that a
 * rolled-back rehearsal (`dry_run`) leaves no trace of what it never did.
 *
 * Versions are read once a request, all groups in one `many()`, and start at a random number
 * rather than 1: a version lost to eviction and reseeded at 1 would meet entries of the old 1.
 */
final class CacheGroups
{
    public const PREFIX = 'webx:ver:';

    /** The frame's own groups: every site has them, whichever modules it installs. */
    public const LOCALES = 'locales';

    public const ROUTES = 'routes';

    public const SCREENS = 'screens';

    public const THEME = 'theme';

    /** The events of a model that move its groups: every save, create included, and the tree's own `moved`. */
    public const EVENTS = ['saved', 'deleted', 'restored', 'forceDeleted', 'moved'];

    /** A version lives this long untouched; one that expired is seeded afresh — a miss, not a stale hit. */
    private const VERSION_TTL = 30 * 86400;

    private const DEFAULT_TTL = 86400;

    /** @var array<string, CacheGroup> */
    private array $groups = [];

    /**
     * The versions read so far this request.
     *
     * @var array<string, int>|null
     */
    private ?array $memo = null;

    /**
     * Groups waiting for a commit, by connection name, so one transaction moves each once.
     *
     * @var array<string, array<string, bool>> whether the move owes the group's hooks too
     */
    private array $pending = [];

    public function __construct(
        private readonly Cache $cache,
        private readonly Dispatcher $events,
        private readonly ConnectionResolverInterface $db,
        private readonly Config $config,
        private readonly Application $app,
    ) {
        // A rolled-back transaction takes its after-commit callbacks with it; the marks that kept
        // a group from being queued twice must go too, or the next transaction would skip it.
        $this->events->listen(TransactionRolledBack::class, function (TransactionRolledBack $event): void {
            unset($this->pending[$event->connectionName]);
        });
    }

    /**
     * Declare a group. Idempotent: a package that only depends on a group declares it too, and
     * whoever comes first with a title names it.
     */
    public function declare(string $group, ?string $title = null): CacheGroup
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $group) !== 1 || strlen($group) > 32) {
            throw new InvalidArgumentException("[{$group}] is not a cache group: lowercase, digits, `-` or `_`, at most 32 characters.");
        }

        $this->groups[$group] ??= new CacheGroup($group, $title);
        $this->groups[$group]->entitle($title);

        return $this->groups[$group];
    }

    /**
     * Move the group's version whenever one of these models is saved, deleted, restored, force
     * deleted or moved in its tree. A path that writes without Eloquent events — a mass
     * `update()`, a raw query, an import — calls `bump()` itself.
     *
     * @param  class-string<Model>  ...$models
     */
    public function bumpOn(string $group, string ...$models): void
    {
        $declared = $this->declare($group);

        foreach ($models as $model) {
            if (! $declared->addModel($model)) {
                continue;
            }

            foreach (self::EVENTS as $event) {
                $this->events->listen("eloquent.{$event}: {$model}", function (Model $record) use ($group): void {
                    $this->bumpFor($record->getConnection(), $group, hooks: false);
                });
            }
        }
    }

    /**
     * Also do this when the group is reset by code — `bump()`, the button, the command, the
     * agent's tool — and not when a model of its own moves it: the bridge for a cache that still
     * lives on a key of its own and forgets itself on its models' events already, so that «reset
     * the site cache» reaches it until it moves onto a versioned key.
     *
     * @param  Closure(): void  $hook
     */
    public function onReset(string $group, Closure $hook): void
    {
        $this->declare($group)->addHook($hook);
    }

    public function has(string $group): bool
    {
        return isset($this->groups[$group]);
    }

    /** @return array<string, CacheGroup> by id, in the order declared */
    public function all(): array
    {
        return $this->groups;
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->groups);
    }

    public function version(string $group): int
    {
        return $this->versions($group)[$group];
    }

    /**
     * The current versions, read once a request for every declared group at a time — a group a
     * key depends on is one query on the `database` store otherwise.
     *
     * @return array<string, int>
     */
    public function versions(string ...$groups): array
    {
        foreach ($groups as $group) {
            $this->known($group);
        }

        if ($this->memo === null) {
            $this->memo = $this->read($this->ids());
        }

        $missing = array_diff($groups, array_keys($this->memo));

        if ($missing !== []) {
            $this->memo += $this->read(array_values($missing));
        }

        return array_intersect_key($this->memo, array_fill_keys($groups, true));
    }

    /**
     * Move the versions of these groups: everything cached on them is a miss from now on.
     * Inside a transaction, after its commit — and not at all if it rolls back.
     */
    public function bump(string ...$groups): void
    {
        foreach ($groups as $group) {
            $this->known($group);
            $this->bumpFor($this->db->connection(), $group);
        }
    }

    public function bumpAll(): void
    {
        $this->bump(...$this->ids());
    }

    /**
     * The key an entry built from these groups is stored under: the versions in it are what
     * makes the entry go when any of them moves. With the language, unless the data is the same
     * in every language.
     *
     * @param  list<string>  $depends
     */
    public function key(string $name, array $depends, bool $localized = true): string
    {
        $versions = $this->versions(...$depends);
        $parts = [];

        foreach ($depends as $group) {
            $parts[] = $group.'.'.$versions[$group];
        }

        $tail = ($localized ? $this->app->getLocale() : '-').':'.implode('.', $parts);

        // The `database` store's key column is 255 characters; a long name with many groups is
        // hashed rather than cut, and what is hashed still changes with every version.
        return 'webx:'.$name.':'.(strlen($name) + strlen($tail) > 180 ? md5($tail) : $tail);
    }

    /**
     * `Cache::remember()` on a versioned key. Nothing is stored inside a transaction: the rows
     * read there may still roll back, and the entry would outlive them.
     *
     * @template T
     *
     * @param  list<string>  $depends  the groups the value is built from
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $name, array $depends, Closure $compute, ?int $ttl = null, bool $localized = true): mixed
    {
        if (! $this->enabled() || $this->db->connection()->transactionLevel() > 0) {
            return $compute();
        }

        return $this->cache->remember($this->key($name, $depends, $localized), $ttl ?? $this->ttl(), $compute);
    }

    /**
     * Forget the versions read this request — one group after it moved, or all of them for a
     * worker that serves the next request in the same process.
     */
    public function forgetMemo(?string $group = null): void
    {
        if ($group === null || $this->memo === null) {
            $this->memo = null;

            return;
        }

        unset($this->memo[$group]);
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('webx-admin.cache.enabled', true);
    }

    /** How long an entry lives unless told otherwise: a day, so that nothing missed stays forever. */
    public function ttl(): int
    {
        return max(1, (int) $this->config->get('webx-admin.cache.ttl', self::DEFAULT_TTL));
    }

    private function bumpFor(ConnectionInterface $connection, string $group, bool $hooks = true): void
    {
        // Whatever this process reads next must not come from before the write it just made.
        $this->forgetMemo($group);

        // Only a real connection has transactions to wait for; anything else moves at once.
        if (! $connection instanceof Connection || $connection->transactionLevel() === 0) {
            $this->move($group, $hooks);

            return;
        }

        $name = (string) $connection->getName();

        if (isset($this->pending[$name][$group])) {
            // Queued already; a reset asked for meanwhile still gets its hooks after the commit.
            $this->pending[$name][$group] = $this->pending[$name][$group] || $hooks;

            return;
        }

        $this->pending[$name][$group] = $hooks;

        $connection->afterCommit(function () use ($name, $group, $hooks): void {
            $withHooks = $this->pending[$name][$group] ?? $hooks;
            unset($this->pending[$name][$group]);
            $this->move($group, $withHooks);
        });
    }

    /**
     * The move itself. `add` first: a version not there yet is seeded — at a random number, which
     * is a move too — and `increment` is atomic on every store that has more than one reader.
     */
    private function move(string $group, bool $hooks): void
    {
        $key = self::PREFIX.$group;

        if (! $this->cache->add($key, self::seed(), self::VERSION_TTL)) {
            $moved = $this->cache->increment($key);

            if ($moved === false) {
                // Gone between the two calls: seeded afresh, which is a move as well.
                $this->cache->put($key, self::seed(), self::VERSION_TTL);
            }
        }

        $this->forgetMemo($group);

        if (! $hooks) {
            return;
        }

        foreach ($this->groups[$group]->hooks() as $hook) {
            $hook();
        }
    }

    /**
     * @param  list<string>  $groups
     * @return array<string, int>
     */
    private function read(array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        $keys = array_map(static fn (string $group): string => self::PREFIX.$group, $groups);
        $found = $this->cache->many($keys);
        $versions = [];

        foreach ($groups as $group) {
            $value = $found[self::PREFIX.$group] ?? null;

            if (! is_numeric($value)) {
                // Somebody else may seed it between our `add` and `get`; theirs then wins, and
                // both of us read one number.
                $this->cache->add(self::PREFIX.$group, self::seed(), self::VERSION_TTL);
                $value = $this->cache->get(self::PREFIX.$group);
            }

            $versions[$group] = is_numeric($value) ? (int) $value : self::seed();
        }

        return $versions;
    }

    private function known(string $group): void
    {
        if (! isset($this->groups[$group])) {
            throw new InvalidArgumentException(sprintf(
                '[%s] is not a declared cache group%s.',
                $group,
                $this->groups === [] ? '' : '; declared: '.implode(', ', $this->ids()),
            ));
        }
    }

    /** Where a version starts: never 1, so that a lost version never meets the keys of its past. */
    private static function seed(): int
    {
        return random_int(1_000_000_000, 8_999_999_999);
    }
}
