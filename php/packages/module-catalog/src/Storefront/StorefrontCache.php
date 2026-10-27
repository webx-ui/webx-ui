<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Facets\FacetValue;

/**
 * The generation of the catalogue and the storefront's caches built on it (WEBX_UI_MODULE_CATALOG.md §8.4).
 *
 * The generation is the version of the `catalog` cache group: every entry here carries it in its
 * key, so one move forgets everything the storefront computed, on any store — `file` and
 * `database` have no tags. It moves on two roads and no third:
 *
 * - a model of the catalogue or of a satellite is saved, deleted, restored or moved — the models
 *   are watched here ({@see watch()}), the satellites name theirs from their providers;
 * - a write without a model's event says which products it changed — {@see Catalog::touch()},
 *   `touchQuery()`, `touchCategory()` — which every such path calls already, because the index of
 *   Manticore needs the same marks. A path that forgot it would be a stale index too.
 *
 * Inside a transaction the move waits for the commit and happens once however many products the
 * transaction saved (the library does that), so a chunk of a bulk action or of an import is one
 * move; {@see defer()} does the same for a batch of work outside one.
 *
 * A cache joins in one line — `remember('labels', [$ids], fn () => …)` — and gets the reset
 * (`cache_reset` with `group: catalog`, «System → Cache») and the line in `debug_page`
 * (`cache.entries`, `catalog.<name>.<hash>`: memo / store / miss) with it.
 */
final class StorefrontCache
{
    public const GROUP = 'catalog';

    /** Whoever defers collects here whether a move is owed; null when nobody does. */
    private ?bool $deferred = null;

    public function __construct(
        private readonly CacheGroups $groups,
        private readonly Config $config,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Move the generation whenever one of these models is saved, deleted, restored or moved — for
     * the core's models and the satellites' alike, so that the group is named in one place.
     *
     * @param  class-string<Model>  ...$models
     */
    public function watch(string ...$models): void
    {
        $group = $this->groups->declare(self::GROUP);

        foreach ($models as $model) {
            if (! $group->addModel($model)) {
                continue;
            }

            // Not the library's `bumpOn()`: a save inside {@see defer()} is part of its one move.
            foreach (CacheGroups::EVENTS as $event) {
                $this->events->listen("eloquent.{$event}: {$model}", fn () => $this->bump());
            }
        }
    }

    public function generation(): int
    {
        return $this->groups->version(self::GROUP);
    }

    /** Everything the storefront cached is a miss from now on; inside a transaction, after its commit. */
    public function bump(): void
    {
        if ($this->deferred !== null) {
            $this->deferred = true;

            return;
        }

        $this->groups->bump(self::GROUP);
    }

    /**
     * One move for all `$work` asks for, when it is done — even when it fails halfway: what it
     * wrote before the failure is written. A nested call is part of the outer one.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function defer(Closure $work): mixed
    {
        if ($this->deferred !== null) {
            return $work();
        }

        $this->deferred = false;

        try {
            return $work();
        } finally {
            $owed = $this->deferred;
            $this->deferred = null;

            if ($owed) {
                $this->bump();
            }
        }
    }

    /** Whether the storefront caches at all: its own switch, and the site's (`WEBX_CACHE_ENABLED`). */
    public function enabled(): bool
    {
        return (bool) $this->config->get('webx-catalog.cache.enabled', true) && $this->groups->enabled();
    }

    /** How long an entry lives if the generation never moves: a safety net, not the way it goes. */
    public function ttl(): int
    {
        return max(60, (int) $this->config->get('webx-catalog.cache.ttl', 86400));
    }

    /**
     * The value of `$compute` for these parts, while the generation stands. The parts are
     * everything the value depends on besides the catalogue — the selection, the filters, the
     * language — and are normalised first: the order of keys in a map and of values in a filter
     * makes no other entry.
     *
     * @template T
     *
     * @param  array<array-key, mixed>  $parts
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $cache, array $parts, Closure $compute): mixed
    {
        if (! $this->enabled()) {
            return $compute();
        }

        return $this->groups->remember(self::name($cache, $parts), [self::GROUP], $compute, $this->ttl(), localized: false);
    }

    /**
     * The name an entry is stored and reported under: the cache and a hash of its parts.
     *
     * @param  array<array-key, mixed>  $parts
     */
    public static function name(string $cache, array $parts): string
    {
        return 'catalog.'.$cache.'.'.md5(json_encode(self::normalise($parts), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * A map sorted by its keys, a filter's values sorted, a range as its two ends — all the way
     * down. A list keeps its order: where it matters (the facets asked for, in the order shown),
     * it is part of the answer.
     */
    public static function normalise(mixed $part): mixed
    {
        if ($part instanceof FacetValue) {
            if ($part->isRange()) {
                return ['min' => $part->min, 'max' => $part->max];
            }

            $values = $part->values;
            sort($values, SORT_STRING);

            return $values;
        }

        if (! is_array($part)) {
            return $part;
        }

        $normal = array_map(self::normalise(...), $part);

        if (! array_is_list($normal)) {
            ksort($normal, SORT_STRING);
        }

        return $normal;
    }
}
