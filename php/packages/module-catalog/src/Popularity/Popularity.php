<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Popularity;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\Product;

/**
 * The two jobs behind the score (§9): writing down the views the cache has counted, and the
 * nightly recount that fades them and weighs every signal into a score.
 *
 * Both write `catalog_product_popularity` and nothing else: a table of its own, so a recount of
 * every product does not rewrite `catalog_products` and wake every `updated_at` with it.
 *
 * The statements are one per batch and built from numbers this class cast itself, so they are
 * portable — MySQL's `on duplicate key update` and sqlite's `on conflict` read the other row
 * differently, and a `case` does not care.
 */
final class Popularity
{
    private const CHUNK = 500;

    public function __construct(
        private readonly ViewCounter $views,
        private readonly PopularitySignals $signals,
        private readonly PopularityFormula $formula,
        private readonly Catalog $catalog,
        private readonly Config $config,
    ) {}

    /**
     * The counted views onto the table. Returns how many products had any.
     */
    public function flush(): int
    {
        $pending = $this->views->take();

        foreach (array_chunk($pending, self::CHUNK, true) as $chunk) {
            DB::transaction(function () use ($chunk): void {
                $this->ensureRows(array_keys($chunk));
                $this->addViews($chunk);
            });
        }

        return count($pending);
    }

    /**
     * Fade the views, then score every live product from every signal, a batch at a time. The
     * products whose score moved by more than the threshold go to the engine: under `SqlEngine`
     * that is nothing, since the sort reads the table itself.
     *
     * Returns how many products were marked.
     */
    public function recount(): int
    {
        $decay = (float) $this->config->get('webx-catalog.popularity.decay', 0.9);
        $threshold = (float) $this->config->get('webx-catalog.popularity.touch_threshold', 0.05);

        if ($decay >= 0 && $decay < 1) {
            DB::table('catalog_product_popularity')->update([
                'views' => DB::raw($this->wrap('views').' * '.$this->number($decay)),
            ]);
        }

        $touched = 0;

        Product::query()->select(['id'])->chunkById(self::CHUNK, function (Collection $products) use ($threshold, &$touched): void {
            /** @var list<int> $ids */
            $ids = array_map('intval', $products->modelKeys());
            $signals = $this->signals->values($ids);
            $old = DB::table('catalog_product_popularity')->whereIn('product_id', $ids)->pluck('score', 'product_id');

            $scores = [];
            $moved = [];

            foreach ($ids as $id) {
                $score = $this->formula->score($id, $signals[$id] ?? []);
                $before = (float) ($old[$id] ?? 0);
                $scores[$id] = $score;

                if (abs($score - $before) > $threshold * max(abs($before), 1.0)) {
                    $moved[] = $id;
                }
            }

            DB::transaction(function () use ($scores): void {
                $this->ensureRows(array_keys($scores));
                $this->setScores($scores);
            });

            $this->catalog->touch($moved);
            $touched += count($moved);
        });

        return $touched;
    }

    /**
     * @param  list<int>  $ids
     */
    private function ensureRows(array $ids): void
    {
        DB::table('catalog_product_popularity')->insertOrIgnore(array_map(
            static fn (int $id): array => ['product_id' => $id, 'views' => 0, 'score' => 0],
            $ids,
        ));
    }

    /**
     * @param  array<int, int>  $views
     */
    private function addViews(array $views): void
    {
        $case = 'case '.$this->wrap('product_id');

        foreach ($views as $id => $count) {
            $case .= ' when '.(int) $id.' then '.(int) $count;
        }

        DB::table('catalog_product_popularity')
            ->whereIn('product_id', array_keys($views))
            ->update(['views' => DB::raw($this->wrap('views').' + '.$case.' else 0 end')]);
    }

    /**
     * @param  array<int, float>  $scores
     */
    private function setScores(array $scores): void
    {
        $case = 'case '.$this->wrap('product_id');

        foreach ($scores as $id => $score) {
            $case .= ' when '.(int) $id.' then '.$this->number($score);
        }

        DB::table('catalog_product_popularity')
            ->whereIn('product_id', array_keys($scores))
            ->update([
                'score' => DB::raw($case.' else 0 end'),
                'computed_at' => Carbon::now(),
            ]);
    }

    private function wrap(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }

    /** A float as SQL reads it, whatever the locale of the process says a decimal point is. */
    private function number(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
