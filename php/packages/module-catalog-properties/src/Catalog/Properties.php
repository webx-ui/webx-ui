<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\CatalogProperties\Models\Property;

/**
 * Every property out of the bin, in its position — what the facet source, the product form and the
 * document read, many times a request (§4.3 of the properties spec).
 *
 * Kept in the `catalog` cache group between requests (WEBX_UI_CACHE.md) and in memory within one.
 * A property's own events move the group's version, so the next read asks the table again; what
 * this process holds — the hydrated list, the core's registry of facets built from it — is
 * dropped by {@see flush()} in the same events, so the process that wrote does not read its own
 * past. A path that writes the positions without the model's events says so with
 * {@see reordered()}.
 */
final class Properties
{
    /** The cache group the list lives in. */
    public const GROUP = 'catalog';

    /** @var array<int, Property>|null */
    private ?array $loaded = null;

    public function __construct(
        private readonly CacheGroups $cache,
        private readonly Facets $facets,
    ) {}

    /**
     * @return array<int, Property> id → property, in position
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->remember('catalog-properties.list', [self::GROUP], static fn (): array => Property::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(static fn (Property $property): array => $property->getAttributes())
            ->all(), localized: false);

        $this->loaded = [];

        foreach ($rows as $row) {
            $property = (new Property)->newFromBuilder($row);
            $this->loaded[(int) $property->id] = $property;
        }

        return $this->loaded;
    }

    public function find(int $id): ?Property
    {
        return $this->all()[$id] ?? null;
    }

    public function byFacet(string $key): ?Property
    {
        $id = Property::idOfFacet($key);

        return $id === null ? null : $this->find($id);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, Property>
     */
    public function only(array $ids): array
    {
        return array_intersect_key($this->all(), array_flip($ids));
    }

    /**
     * A property was saved: what this process holds of the list is stale, here and in the
     * registry of facets. The cached list goes with the group's version, which the model's own
     * events move.
     */
    public function flush(): void
    {
        $this->loaded = null;
        $this->facets->flush();
    }

    /** The positions were written without the model's events: the group is told here. */
    public function reordered(): void
    {
        $this->flush();
        $this->cache->bump(self::GROUP);
    }

    /** Forget only what this process remembers — a queue worker, between jobs. */
    public function reset(): void
    {
        $this->loaded = null;
    }
}
