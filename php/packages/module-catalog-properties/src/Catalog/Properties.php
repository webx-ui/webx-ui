<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use Illuminate\Contracts\Cache\Repository as Cache;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\CatalogProperties\Models\Property;

/**
 * Every property out of the bin, in its position — what the facet source, the product form and the
 * document read, many times a request (§4.3 of the properties spec).
 *
 * Kept in the cache between requests and in memory within one, and forgotten whenever a property
 * is saved, deleted or restored: the next read asks the table again, and the core's registry of
 * facets asks its sources again too.
 */
final class Properties
{
    private const GENERATION = 'webx.catalog-properties.generation';

    /** @var array<int, Property>|null */
    private ?array $loaded = null;

    public function __construct(
        private readonly Cache $cache,
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

        $key = 'webx.catalog-properties.list.'.$this->generation();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->rememberForever($key, static fn (): array => Property::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(static fn (Property $property): array => $property->getAttributes())
            ->all());

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

    /** A property was saved: every list is stale, here and in the registry of facets. */
    public function flush(): void
    {
        $this->loaded = null;
        $this->cache->forever(self::GENERATION, bin2hex(random_bytes(6)));
        $this->facets->flush();
    }

    /** Forget only what this process remembers — a queue worker, between jobs. */
    public function reset(): void
    {
        $this->loaded = null;
    }

    private function generation(): string
    {
        $generation = $this->cache->get(self::GENERATION);

        return is_string($generation) ? $generation : '0';
    }
}
