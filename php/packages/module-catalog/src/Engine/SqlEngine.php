<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Sorts\Sorts;

/**
 * The database as the engine (§8.2) — honest up to a couple of thousand live products, which is
 * most shops; past `sql_engine_limit` `webx:doctor` says it is time for Manticore.
 *
 * For a question with N facets to count it asks N + 2 queries: the page, the total, and one
 * `group by` per facet with every filter but that facet's own. The category of the page is a
 * subtree by `lft/rgt`, main and additional categories alike, so a parent holds everything below.
 *
 * It keeps no index, because the tables are one: {@see needsIndex()} is false, and nothing is
 * ever queued for it.
 */
final class SqlEngine implements CatalogEngine
{
    public function __construct(
        private readonly Facets $facets,
        private readonly Sorts $sorts,
    ) {}

    public function search(CatalogQuery $query): CatalogResult
    {
        $base = $this->base($query);
        $total = (clone $base)->count();
        $ids = [];

        if ($total > 0) {
            $page = clone $base;
            $this->sorts->resolve($query->sort)->applySql($page, $query->locale);

            $ids = $page
                ->orderByDesc($page->getModel()->qualifyColumn('id'))
                ->forPage(max(1, $query->page), max(1, $query->perPage))
                ->pluck($page->getModel()->qualifyColumn('id'))
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        $facets = [];

        foreach ($query->count as $key) {
            $result = $this->count($query, $key);

            if ($result !== null) {
                $facets[$key] = $result;
            }
        }

        return new CatalogResult($ids, $total, $facets);
    }

    public function needsIndex(): bool
    {
        return false;
    }

    public function prepare(array $fields, bool $rebuild = false): void {}

    public function index(array $documents): void {}

    public function remove(array $ids): void {}

    /**
     * The products the question is about, with every filter applied but the one named — a facet's
     * own choice does not narrow its own counts.
     *
     * @return Builder<Product>
     */
    private function base(CatalogQuery $query, ?string $except = null): Builder
    {
        $products = $query->onlyTrashed ? Product::onlyTrashed() : Product::query();
        $table = $products->getModel()->getTable();

        if (! $query->withUnpublished && ! $query->onlyTrashed) {
            $products->visible();
        }

        match ($query->state) {
            CatalogQuery::STATE_PUBLISHED => $products->where($table.'.is_published', true),
            CatalogQuery::STATE_UNPUBLISHED => $products->where($table.'.is_published', false),
            CatalogQuery::STATE_NO_CATEGORY => $products->whereNull($table.'.category_id'),
            default => null,
        };

        $term = trim((string) $query->search);

        if ($term !== '') {
            $products->matching($term);
        }

        // Where the page stands narrows everything, the counts included.
        foreach ($query->scope as $key => $value) {
            $this->facets->find($key)?->applySql($products, $value);
        }

        foreach ($query->facets as $key => $value) {
            if ($key === $except || $value->isEmpty()) {
                continue;
            }

            $this->facets->find($key)?->applySql($products, $value);
        }

        return $products;
    }

    private function count(CatalogQuery $query, string $key): ?FacetResult
    {
        $facet = $this->facets->find($key);

        if ($facet === null) {
            return null;
        }

        $products = $this->base($query, except: $key);
        $ids = $products->toBase()->select($products->getModel()->qualifyColumn('id'));
        $pairs = DB::connection($products->getModel()->getConnectionName())->query()
            ->fromSub($facet->sqlValues($ids), 'pairs');

        return match ($facet->kind()) {
            FacetKind::Range => $this->range($key, $pairs),
            FacetKind::Toggle => new FacetResult($key, FacetKind::Toggle, count: (int) $pairs->distinct()->count('product_id')),
            default => new FacetResult($key, $facet->kind(), counts: $this->counts($pairs)),
        };
    }

    private function range(string $key, QueryBuilder $pairs): FacetResult
    {
        $ends = $pairs->selectRaw('min(value) as low, max(value) as high')->first();

        return new FacetResult(
            $key,
            FacetKind::Range,
            min: is_numeric($ends->low ?? null) ? (float) $ends->low : null,
            max: is_numeric($ends->high ?? null) ? (float) $ends->high : null,
        );
    }

    /**
     * @return array<string, int>
     */
    private function counts(QueryBuilder $pairs): array
    {
        return $pairs
            ->select('value')
            ->selectRaw('count(distinct product_id) as products')
            ->groupBy('value')
            ->pluck('products', 'value')
            ->mapWithKeys(static fn (mixed $count, mixed $value): array => [(string) $value => (int) $count])
            ->all();
    }
}
