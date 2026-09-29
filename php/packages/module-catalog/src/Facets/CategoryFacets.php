<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Category;

/**
 * Which facets a category shows, and in what order (§6.2).
 *
 * Its own settings if it has any; otherwise those of the nearest ancestor that has; otherwise
 * every facet of the registry in the registry's order. One query over the ancestors, deepest
 * first, and the answer kept in the cache per category.
 *
 * A facet registered after a category was configured is hidden in that category until somebody
 * switches it on: a module installed later must not change a filter an editor arranged by hand.
 * A key in the settings whose module is gone is skipped, not dropped — the settings keep it, and
 * putting the module back brings the facet back where it was.
 *
 * Every setting shares one generation in the cache, and any save of any setting — or a move of
 * a category, which changes whom it inherits from — moves it on. A per-category key would have to
 * forget every descendant as well, and the tree is the one thing a cache key cannot see.
 */
final class CategoryFacets
{
    private const GENERATION = 'webx.catalog.facets.generation';

    public function __construct(
        private readonly Facets $facets,
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    /**
     * The facets to draw, in order.
     *
     * @return list<Facet>
     */
    public function visible(?Category $category): array
    {
        $resolved = $this->resolve($category);

        if ($resolved['facets'] === null) {
            return $this->facets->all();
        }

        $visible = [];

        foreach ($resolved['facets'] as $row) {
            $facet = $this->facets->find($row['key']);

            if ($facet !== null && $row['visible']) {
                $visible[] = $facet;
            }
        }

        return $visible;
    }

    /**
     * The settings in force and whose they are: the category's own, an ancestor's, or none at all
     * (`from` null and `facets` null — every facet by default).
     *
     * @return array{from: int|null, facets: list<array{key: string, visible: bool}>|null}
     */
    public function resolve(?Category $category): array
    {
        // Switched off, the tab is gone, and settings nobody can see or change must not decide
        // what a visitor is offered.
        if ($category === null || ! $category->exists || ! $this->enabled()) {
            return ['from' => null, 'facets' => null];
        }

        $key = 'webx.catalog.facets.'.$this->generation().'.'.$category->getKey();

        /** @var array{from: int|null, facets: list<array{key: string, visible: bool}>|null} $resolved */
        $resolved = $this->cache->rememberForever($key, fn (): array => $this->lookup($category));

        return $resolved;
    }

    /** Every category's answer is stale: a setting was saved or a branch moved. */
    public function forget(): void
    {
        $this->cache->forever(self::GENERATION, bin2hex(random_bytes(6)));
    }

    /**
     * @return array{from: int|null, facets: list<array{key: string, visible: bool}>|null}
     */
    private function lookup(Category $category): array
    {
        // Fresh bounds: a node loaded before something was added under its ancestors has stale
        // ones in memory (docs/pitfalls).
        $bounds = Category::withTrashed()->whereKey($category->getKey())->first(['lft', 'rgt']);

        if (! $bounds instanceof Category) {
            return ['from' => null, 'facets' => null];
        }

        $rows = DB::table('catalog_category_facets as facets')
            ->join('catalog_categories as owner', 'owner.id', '=', 'facets.category_id')
            ->where('owner.lft', '<=', $bounds->lft)
            ->where('owner.rgt', '>=', $bounds->rgt)
            ->orderByDesc('owner.depth')
            ->orderBy('facets.position')
            ->orderBy('facets.id')
            ->get(['facets.category_id', 'facets.facet_key', 'facets.is_visible']);

        if ($rows->isEmpty()) {
            return ['from' => null, 'facets' => null];
        }

        $from = (int) $rows->first()->category_id;
        $facets = [];

        foreach ($rows as $row) {
            if ((int) $row->category_id !== $from) {
                break;
            }

            $facets[] = ['key' => (string) $row->facet_key, 'visible' => (bool) $row->is_visible];
        }

        return ['from' => $from, 'facets' => $facets];
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('webx-catalog.fields.facets', false);
    }

    private function generation(): string
    {
        $generation = $this->cache->get(self::GENERATION);

        if (is_string($generation) && $generation !== '') {
            return $generation;
        }

        $this->forget();

        return (string) $this->cache->get(self::GENERATION, '0');
    }
}
