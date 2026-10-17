<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use WebxUi\Cache\CacheGroups;
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
 * A facet of a source ({@see FacetSource}) is not a module installed later but something an editor
 * made a filter on purpose — a property put into a category's set and marked «in the filter» — so
 * the settings that do not mention it show it last, and its source decides whether it belongs
 * (§4.4 of the properties spec). Hidden in the settings, it stays hidden.
 * A key in the settings whose module is gone is skipped, not dropped — the settings keep it, and
 * putting the module back brings the facet back where it was.
 *
 * Every category's answer is worked out at once and kept as one entry of the `catalog` cache
 * group (WEBX_UI_CACHE.md): a save of any setting ({@see forget()}) or of any category — a move
 * changes whom a branch inherits from — moves the group's version, and the entry with it. A
 * per-category key would have to forget every descendant as well, and the tree is the one thing
 * a cache key cannot see.
 */
final class CategoryFacets
{
    /** The cache group the settings live in. */
    public const GROUP = 'catalog';

    public function __construct(
        private readonly Facets $facets,
        private readonly CacheGroups $cache,
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
        $mentioned = [];

        foreach ($resolved['facets'] as $row) {
            $facet = $this->facets->find($row['key']);
            $mentioned[$row['key']] = true;

            if ($facet !== null && $row['visible']) {
                $visible[] = $facet;
            }
        }

        foreach ($this->facets->all() as $facet) {
            if (! isset($mentioned[$facet->key()]) && $this->facets->sourceOf($facet->key()) !== null) {
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

        /** @var array<int, array{from: int|null, facets: list<array{key: string, visible: bool}>|null}> $all */
        $all = $this->cache->remember('catalog.facets', [self::GROUP], fn (): array => $this->lookup(), localized: false);

        return $all[(int) $category->getKey()] ?? ['from' => null, 'facets' => null];
    }

    /** Every category's answer is stale: a setting was written without a model's event. */
    public function forget(): void
    {
        $this->cache->bump(self::GROUP);
    }

    /**
     * Whose settings each category is under, for every category at once: the rows of the table
     * with their owners' bounds, matched against every category's bounds — two queries once per
     * version, rather than two per category per request. The nearest owner wins.
     *
     * @return array<int, array{from: int|null, facets: list<array{key: string, visible: bool}>|null}>
     */
    private function lookup(): array
    {
        $rows = DB::table('catalog_category_facets as facets')
            ->join('catalog_categories as owner', 'owner.id', '=', 'facets.category_id')
            ->orderByDesc('owner.depth')
            ->orderBy('facets.position')
            ->orderBy('facets.id')
            ->get(['facets.category_id', 'facets.facet_key', 'facets.is_visible', 'owner.lft', 'owner.rgt']);

        if ($rows->isEmpty()) {
            return [];
        }

        /** @var array<int, array{lft: int, rgt: int, facets: list<array{key: string, visible: bool}>}> $owners deepest first */
        $owners = [];

        foreach ($rows as $row) {
            $owners[(int) $row->category_id] ??= ['lft' => (int) $row->lft, 'rgt' => (int) $row->rgt, 'facets' => []];
            $owners[(int) $row->category_id]['facets'][] = ['key' => (string) $row->facet_key, 'visible' => (bool) $row->is_visible];
        }

        $resolved = [];

        // Fresh bounds of every category, the bin included: a node loaded before something was
        // added under its ancestors has stale ones in memory (docs/pitfalls).
        foreach (Category::withTrashed()->toBase()->get(['id', 'lft', 'rgt']) as $category) {
            foreach ($owners as $id => $owner) {
                if ($owner['lft'] <= (int) $category->lft && $owner['rgt'] >= (int) $category->rgt) {
                    $resolved[(int) $category->id] = ['from' => $id, 'facets' => $owner['facets']];

                    break;
                }
            }
        }

        return $resolved;
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('webx-catalog.fields.facets', false);
    }
}
