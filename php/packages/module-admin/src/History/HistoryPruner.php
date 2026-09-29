<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;

/**
 * What keeps the journal from growing without end (§7).
 *
 * In batches, so that a year of an hourly price import is a few thousand short deletes at night
 * rather than one statement that locks the table the panel is writing to.
 *
 * A run goes whole or not at all, and by its own date: its rows were written after it, so
 * judging them by theirs would cut the tail off a run that started just before the line.
 */
final readonly class HistoryPruner
{
    public const BATCH = 1000;

    public function __construct(private Config $config) {}

    public function days(): int
    {
        return max(1, (int) $this->config->get('webx-admin.history.retention_days', 365));
    }

    /**
     * @return int How many rows went.
     */
    public function prune(?Carbon $now = null, int $batch = self::BATCH): int
    {
        $before = ($now ?? Carbon::now())->copy()->subDays($this->days());
        $removed = 0;

        // Rows of nobody's run.
        do {
            $ids = HistoryEntry::query()
                ->whereNull('parent_id')
                ->where('event', '!=', HistoryEntry::RUN)
                ->where('created_at', '<', $before)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id');

            $removed += $ids->isEmpty() ? 0 : HistoryEntry::query()->whereKey($ids->all())->delete();
        } while ($ids->count() === $batch);

        // Old runs, each with everything under it — its rows before itself, so that the key
        // never has anything to say about the order.
        do {
            $runs = HistoryEntry::query()
                ->where('event', HistoryEntry::RUN)
                ->where('created_at', '<', $before)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id');

            foreach ($runs as $run) {
                $removed += $this->pruneRun((int) $run, $batch);
            }
        } while ($runs->count() === $batch);

        return $removed;
    }

    private function pruneRun(int $run, int $batch): int
    {
        $removed = 0;

        do {
            $events = HistoryEntry::query()->where('parent_id', $run)->orderBy('id')->limit($batch)->pluck('event', 'id');
            $fetched = $events->count();

            foreach ($events as $id => $event) {
                // A run inside the run goes the same way, rows first.
                if ($event === HistoryEntry::RUN) {
                    $removed += $this->pruneRun((int) $id, $batch);
                    $events->forget($id);
                }
            }

            $ids = $events->keys();
            $removed += $ids->isEmpty() ? 0 : HistoryEntry::query()->whereKey($ids->all())->delete();
        } while ($fetched === $batch);

        return $removed + HistoryEntry::query()->whereKey($run)->delete();
    }
}
