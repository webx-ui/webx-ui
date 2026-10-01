<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\Locales;

/**
 * The worker behind `webx:catalog:index` (§8.3): the queue in batches of five hundred, every
 * contributor's share of a batch in one pass, and the documents to the engine.
 *
 * A batch leaves the queue before the engine is asked and goes back into it if the engine refuses.
 * The other order — remove after the answer — loses a save made while the batch was being built:
 * its mark is `insert ignore`d onto the row that is about to be deleted. This way the mark lands
 * in an empty place and waits for the next pass. Doing a batch twice is harmless: a document
 * written again replaces itself.
 *
 * Products in the bin are indexed too, as deleted — «Deleted» searches with the same engine — and
 * a product that is gone from the table altogether is removed from the index.
 */
final class Indexer
{
    public const CHUNK = 500;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Documents $documents,
        private readonly Locales $locales,
    ) {}

    /**
     * Everything the queue holds, batch by batch. Returns how many products were written.
     */
    public function run(int $chunk = self::CHUNK): int
    {
        if (! $this->catalog->needsIndex()) {
            return 0;
        }

        $engine = $this->catalog->engine();
        $engine->prepare($this->documents->schema($this->locales->codes()));
        $done = 0;

        while (true) {
            $ids = DB::table('catalog_index_queue')
                ->orderBy('queued_at')
                ->orderBy('product_id')
                ->limit($chunk)
                ->pluck('product_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($ids === []) {
                return $done;
            }

            DB::table('catalog_index_queue')->whereIn('product_id', $ids)->delete();

            try {
                $done += $this->write($engine, $ids);
            } catch (Throwable $failure) {
                $this->catalog->touch($ids);

                throw $failure;
            }
        }
    }

    /**
     * The whole catalogue from scratch, with the schema made anew. The queue is emptied first:
     * everything in it is about to be written anyway.
     *
     * An engine that rebuilds aside ({@see RebuildsAside}) swaps the new index in at the end. A
     * product saved while a batch holding it was being built would be written stale into the new
     * index, so whatever changed since the start is queued again once the swap is done.
     *
     * `$progress` is told after every batch how many are written of how many — the panel's bar
     * while a rebuild runs as a job.
     *
     * @param  (Closure(int, int): void)|null  $progress  written, of all
     */
    public function rebuild(int $chunk = self::CHUNK, ?Closure $progress = null): int
    {
        $engine = $this->catalog->engine();
        $started = Carbon::now()->subSecond();
        $engine->prepare($this->documents->schema($this->locales->codes()), rebuild: true);

        DB::table('catalog_index_queue')->delete();

        $done = 0;
        $total = $progress === null ? 0 : Product::withTrashed()->count();

        if ($progress !== null) {
            $progress(0, $total);
        }

        Product::withTrashed()->select(['id'])->chunkById($chunk, function (Collection $products) use ($engine, &$done, &$total, $progress): void {
            $done += $this->write($engine, array_map('intval', $products->modelKeys()));

            if ($progress !== null) {
                // Products made meanwhile are written too: the bar never runs past its end.
                $total = max($total, $done);
                $progress($done, $total);
            }
        });

        if ($engine instanceof RebuildsAside) {
            $engine->completeRebuild();
            $this->catalog->touchQuery(Product::withTrashed()->where('updated_at', '>=', $started));
        }

        return $done;
    }

    /**
     * @param  list<int>  $ids
     */
    private function write(CatalogEngine $engine, array $ids): int
    {
        /** @var Collection<int, Product> $products */
        $products = Product::withTrashed()->whereKey($ids)->get();
        $found = array_map('intval', $products->modelKeys());

        $engine->index($this->documents->build($products, $this->locales->codes()));

        $gone = array_values(array_diff($ids, $found));

        if ($gone !== []) {
            $engine->remove($gone);
        }

        return count($found);
    }
}
