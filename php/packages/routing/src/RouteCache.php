<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Cache\CacheGroups;
use WebxUi\Routing\Models\Route;

/**
 * The canonical addresses `url()` answers from, for an entity whose `routes` were not loaded.
 *
 * Three layers, because a list is where addresses are asked for and a list of twelve articles
 * with their tags was forty queries:
 *
 * 1. this request's memory — an entity asked twice (a tag on every card, the trail and the
 *    `hreflang` lines) is asked of nothing else the second time;
 * 2. the site's cache — every key a list needs in one `many()`, which on Redis is one `MGET`;
 * 3. the registry — one query a type for what the cache did not have, written back, «no address»
 *    included, so that an entity without one is not asked about on every page either.
 *
 * The keys carry the version of the `routes` cache group (`webx:route:v1:<type>:<id>:-:routes.N`):
 * every write to the registry moves it — a save through the model, `RouteSync`'s own bulk writes,
 * `webx:routes:rebuild` — so a changed address is never read from the cache and nothing is ever
 * flushed. Coarse on purpose: an address depends on its parent's and a module's prefix, and a
 * key a write would have to name one by one is a key a write forgets. `v1` is the shape of the
 * value; a package that changes it moves to `v2` rather than reading what `v1` wrote.
 *
 * Scoped, not a singleton: the memory is one request's or one job's, never the next one's.
 */
final class RouteCache
{
    public const FORMAT = 'v1';

    /**
     * Canonical rows by `type:id`; an empty list is an entity known to have no address.
     *
     * @var array<string, list<Route>>
     */
    private array $memo = [];

    /** The version of the `routes` group the memory was read under. */
    private ?int $generation = null;

    public function __construct(
        private readonly CacheGroups $groups,
        private readonly Cache $cache,
        private readonly ConnectionResolverInterface $db,
    ) {}

    /**
     * The canonical rows of this entity, one a language.
     *
     * @return list<Route>
     */
    public function canonical(Model $entity): array
    {
        $this->settle();
        $id = self::id($entity);

        if (! array_key_exists($id, $this->memo)) {
            $this->load([$id => [$entity->getMorphClass(), $entity->getKey()]]);
        }

        return $this->memo[$id] ?? [];
    }

    /**
     * Read the addresses of all these entities at once — what a list calls before it asks each
     * card for its `url()`. An entity with its `routes` loaded already answers from them and is
     * skipped, so is one not saved yet.
     *
     * @param  iterable<Model>  $entities
     */
    public function preload(iterable $entities): void
    {
        $this->settle();
        $wanted = [];

        foreach ($entities as $entity) {
            if (! $entity->exists || $entity->relationLoaded('routes') || ! method_exists($entity, 'routeCanonical')) {
                continue;
            }

            $id = self::id($entity);

            if (! array_key_exists($id, $this->memo)) {
                $wanted[$id] = [$entity->getMorphClass(), $entity->getKey()];
            }
        }

        if ($wanted !== []) {
            $this->load($wanted);
        }
    }

    /** Forget this request's memory: the registry was just written. */
    public function forget(): void
    {
        $this->memo = [];
    }

    /** @param  array<string, array{string, mixed}>  $wanted  `type:id` to the type and the key */
    private function load(array $wanted): void
    {
        $shared = $this->shared();
        $keys = [];

        if ($shared) {
            foreach (array_keys($wanted) as $id) {
                $keys[$id] = $this->groups->key('route:'.self::FORMAT.':'.$id, [CacheGroups::ROUTES], localized: false);
            }

            $found = $this->cache->many(array_values($keys));

            foreach ($keys as $id => $key) {
                // A list, empty or not, is an answer; null is a key the cache does not have.
                if (is_array($found[$key] ?? null)) {
                    $this->memo[$id] = $this->hydrate($found[$key]);
                    unset($wanted[$id]);
                }
            }
        }

        $byType = [];

        foreach ($wanted as $id => [$type, $key]) {
            $byType[$type][$id] = $key;
        }

        $store = [];

        foreach ($byType as $type => $ids) {
            $rows = array_fill_keys(array_keys($ids), []);

            $routes = Route::query()
                ->where('entity_type', $type)
                ->whereIn('entity_id', array_values($ids))
                ->canonical()
                ->orderBy('id')
                ->get();

            foreach ($routes as $route) {
                $rows[$type.':'.$route->entity_id][] = $route;
            }

            foreach ($rows as $id => $found) {
                $this->memo[$id] = $found;

                if ($shared) {
                    // Scalars, not models: what another version of the model would still read.
                    $store[$keys[$id]] = array_map(static fn (Route $route): array => $route->getAttributes(), $found);
                }
            }
        }

        if ($store !== []) {
            $this->cache->putMany($store, $this->groups->ttl());
        }
    }

    /**
     * @param  array<mixed>  $rows
     * @return list<Route>
     */
    private function hydrate(array $rows): array
    {
        $routes = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $routes[] = (new Route)->newFromBuilder($row);
            }
        }

        return $routes;
    }

    /**
     * Whether the site's cache may be read and written: not when it is turned off, and not inside
     * a transaction — the rows read there may still roll back, and the cache holds what was
     * committed, not what this transaction wrote.
     */
    private function shared(): bool
    {
        return $this->groups->enabled() && $this->db->connection()->transactionLevel() === 0;
    }

    /** The memory is good for one version of the group; another process may have moved it. */
    private function settle(): void
    {
        $version = $this->groups->version(CacheGroups::ROUTES);

        if ($version !== $this->generation) {
            $this->memo = [];
            $this->generation = $version;
        }
    }

    private static function id(Model $entity): string
    {
        return $entity->getMorphClass().':'.(string) $entity->getKey();
    }
}
