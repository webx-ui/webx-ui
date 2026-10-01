<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Facets\BatchCountedFacet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Search\SearchContributors;
use WebxUi\Catalog\Sorts\Sorts;

/**
 * The database as the engine (§8.2) — honest up to a couple of thousand live products, which is
 * most shops; past `sql_engine_limit` `webx:doctor` says it is time for Manticore.
 *
 * For a question with N facets to count it asks N + 2 queries at most: the page, the total, and a
 * `group by` per facet with every filter but that facet's own. Facets that count together
 * ({@see BatchCountedFacet}) and are not chosen share one query per source and kind — they all
 * count over the same products. The category of the page is a subtree by `lft/rgt`, main and
 * additional categories alike, so a parent holds everything below.
 *
 * Before counting, a source that picks its facets ({@see FacetPlacement}) is asked which of them
 * belong on the page; the rest are not counted at all. A chosen facet always is, and always open.
 *
 * A search is `LIKE`, and nothing more (decision 18 of the Manticore spec): no corrections. A
 * product whose code is the search itself comes first all the same, and is named in the answer.
 *
 * It keeps no index, because the tables are one: {@see needsIndex()} is false, and nothing is
 * ever queued for it.
 */
final class SqlEngine implements CatalogEngine
{
    public function __construct(
        private readonly Facets $facets,
        private readonly Sorts $sorts,
        private readonly SearchContributors $search,
        private readonly FacetPlacement $placement,
    ) {}

    public function search(CatalogQuery $query): CatalogResult
    {
        $base = $this->base($query);
        $total = (clone $base)->count();
        $term = trim((string) $query->search);
        $ids = [];
        $exact = [];

        if ($total > 0) {
            $page = clone $base;

            if ($term !== '') {
                $exact = (clone $base)->coded($term)->limit(2)->pluck($base->getModel()->qualifyColumn('id'))
                    ->map(static fn (mixed $id): int => (int) $id)->values()->all();
            }

            if ($exact !== []) {
                $model = $page->getModel();
                $page->orderByRaw(
                    'CASE WHEN '.$model->qualifyColumn('sku').' = ? OR '.$model->qualifyColumn('barcode').' = ? OR '.$model->qualifyColumn('external_id').' = ? THEN 0 ELSE 1 END',
                    [$term, $term, $term],
                );
            }

            $this->sorts->resolve($query->sort)->applySql($page, $query->locale);

            $ids = $page
                ->orderByDesc($page->getModel()->qualifyColumn('id'))
                ->forPage(max(1, $query->page), max(1, $query->perPage))
                ->pluck($page->getModel()->qualifyColumn('id'))
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        return new CatalogResult($ids, $total, $query->count === [] ? [] : $this->countAll($query, $base), $exact);
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
            $contributors = $this->search->all();

            // The core's fields, or any satellite's: one group of `or`s.
            $products->where(static function (Builder $any) use ($term, $contributors, $query): void {
                $any->matching($term);

                foreach ($contributors as $contributor) {
                    $any->orWhere(static function (Builder $one) use ($contributor, $term, $query): void {
                        $contributor->applySql($one, $term, $query->locale);
                    });
                }
            });
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

    /**
     * Every facet asked about that belongs on the page, in the order asked.
     *
     * @param  Builder<Product>  $base
     * @return array<string, FacetResult>
     */
    private function countAll(CatalogQuery $query, Builder $base): array
    {
        $found = $this->ids($base);
        $placing = $this->placement->place($query, $found);
        $single = [];
        /** @var array<string, array{facet: BatchCountedFacet, kind: FacetKind, keys: list<string>}> $batches */
        $batches = [];

        foreach ($query->count as $key) {
            $facet = $this->facets->find($key);

            if ($facet === null || ($placing !== null && ! isset($placing[$key]))) {
                continue;
            }

            if ($facet instanceof BatchCountedFacet && ! FacetPlacement::chosen($query, $key)) {
                $source = $this->facets->sourceOf($key);
                $group = ($source === null ? $facet::class : spl_object_id($source)).'|'.$facet->kind()->value;
                $batches[$group] ??= ['facet' => $facet, 'kind' => $facet->kind(), 'keys' => []];
                $batches[$group]['keys'][] = $key;

                continue;
            }

            $single[] = $key;
        }

        $counted = [];

        foreach ($single as $key) {
            $result = $this->count($query, $key);

            if ($result !== null) {
                $counted[$key] = $result;
            }
        }

        foreach ($batches as $batch) {
            $counted += $this->countMany($batch['facet'], $batch['kind'], $batch['keys'], $found, $base);
        }

        return FacetPlacement::arrange($query, $counted, $placing);
    }

    /**
     * @param  Builder<Product>  $products
     */
    private function ids(Builder $products): QueryBuilder
    {
        return (clone $products)->toBase()->select($products->getModel()->qualifyColumn('id'));
    }

    private function count(CatalogQuery $query, string $key): ?FacetResult
    {
        $facet = $this->facets->find($key);

        if ($facet === null) {
            return null;
        }

        $products = $this->base($query, except: $key);
        $pairs = $this->over($products)->fromSub($facet->sqlValues($this->ids($products)), 'pairs');

        return match ($facet->kind()) {
            FacetKind::Range => $this->range($key, $pairs),
            FacetKind::Toggle => new FacetResult($key, FacetKind::Toggle, count: (int) $pairs->distinct()->count('product_id')),
            default => new FacetResult($key, $facet->kind(), counts: $this->counts($pairs)),
        };
    }

    /**
     * Facets of one source and one kind, none of them chosen, in one query: they all count over
     * the same products, so one `group by facet_key` answers for all.
     *
     * @param  list<string>  $keys
     * @param  Builder<Product>  $base
     * @return array<string, FacetResult>
     */
    private function countMany(BatchCountedFacet $facet, FacetKind $kind, array $keys, QueryBuilder $found, Builder $base): array
    {
        $rows = $this->over($base)->fromSub($facet->sqlValuesMany($keys, clone $found), 'pairs')->groupBy('facet_key');

        $results = [];

        if ($kind === FacetKind::Range) {
            $ends = $rows->select('facet_key')->selectRaw('min(value) as low, max(value) as high')->get()->keyBy('facet_key');

            foreach ($keys as $key) {
                $row = $ends[$key] ?? null;
                $results[$key] = new FacetResult(
                    $key,
                    $kind,
                    min: is_numeric($row->low ?? null) ? (float) $row->low : null,
                    max: is_numeric($row->high ?? null) ? (float) $row->high : null,
                );
            }

            return $results;
        }

        if ($kind === FacetKind::Toggle) {
            $counts = $rows->select('facet_key')->selectRaw('count(distinct product_id) as products')->pluck('products', 'facet_key');

            foreach ($keys as $key) {
                $results[$key] = new FacetResult($key, $kind, count: (int) ($counts[$key] ?? 0));
            }

            return $results;
        }

        $counts = array_fill_keys($keys, []);

        foreach ($rows->groupBy('value')->select(['facet_key', 'value'])->selectRaw('count(distinct product_id) as products')->get() as $row) {
            $counts[(string) $row->facet_key][(string) $row->value] = (int) $row->products;
        }

        foreach ($keys as $key) {
            $results[$key] = new FacetResult($key, $kind, counts: $counts[$key]);
        }

        return $results;
    }

    /**
     * @param  Builder<Product>  $products
     */
    private function over(Builder $products): QueryBuilder
    {
        return DB::connection($products->getModel()->getConnectionName())->query();
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
