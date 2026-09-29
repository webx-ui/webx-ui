<?php

declare(strict_types=1);

namespace WebxUi\Catalog;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Engine\CatalogEngine;
use WebxUi\Catalog\Engine\CatalogEngines;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;

/**
 * The engine the site chose, and the marks that tell it what to reindex (§8.3).
 *
 * A mark is a row in `catalog_index_queue` and nothing else: no call to the engine, so a save
 * never waits for it and never fails because of it (§5.2 of the architecture). Whether marks are
 * written at all is the engine's to say — under `SqlEngine` the database is the index, and
 * nothing is written.
 */
final class Catalog
{
    private ?CatalogEngine $engine = null;

    public function __construct(
        private readonly Config $config,
        private readonly CatalogEngines $engines,
    ) {}

    public function engine(): CatalogEngine
    {
        return $this->engine ??= $this->engines->make((string) $this->config->get('webx-catalog.engine', 'sql'));
    }

    public function needsIndex(): bool
    {
        return $this->engine()->needsIndex();
    }

    /**
     * @param  iterable<int|string>  $ids
     */
    public function touch(iterable $ids): void
    {
        if (! $this->needsIndex()) {
            return;
        }

        $now = Carbon::now();
        $rows = [];

        foreach ($ids as $id) {
            $rows[(int) $id] = ['product_id' => (int) $id, 'queued_at' => $now];
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            DB::table('catalog_index_queue')->insertOrIgnore($chunk);
        }
    }

    /**
     * Every product the query finds, in one `insert … select` — for a reference book renamed or a
     * category hidden, where a loop would be a statement per product.
     *
     * @param  Builder<Product>  $products
     */
    public function touchQuery(Builder $products): void
    {
        if (! $this->needsIndex()) {
            return;
        }

        $select = (clone $products)
            ->withoutGlobalScopes()
            ->toBase()
            ->select([$products->getModel()->qualifyColumn('id')])
            ->selectRaw('? as queued_at', [Carbon::now()]);

        DB::table('catalog_index_queue')->insertOrIgnoreUsing(['product_id', 'queued_at'], $select);
    }

    /**
     * The products a category's publication changes — filed under it or anywhere below it, as the
     * main or an additional category (§6.3).
     */
    public function touchCategory(Category $category): void
    {
        if (! $this->needsIndex()) {
            return;
        }

        $this->touchQuery(Product::withTrashed()->inCategories($category->subtree()->withTrashed()->pluck('id')->all()));
    }

    /**
     * How many products wait for the engine, and since when the oldest has.
     *
     * @return array{waiting: int, oldest: Carbon|null}
     */
    public function queue(): array
    {
        $row = DB::table('catalog_index_queue')->selectRaw('count(*) as waiting, min(queued_at) as oldest')->first();

        return [
            'waiting' => (int) ($row->waiting ?? 0),
            'oldest' => isset($row->oldest) ? Carbon::parse((string) $row->oldest) : null,
        ];
    }
}
