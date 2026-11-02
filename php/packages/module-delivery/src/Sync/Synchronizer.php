<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Throwable;
use WebxUi\Cache\CacheGroups;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Geography\LocalityKind;
use WebxUi\Geography\Models\GeoLocality;

/**
 * Pulls a carrier's directory page by page and writes it (§7): the run in the journal, one
 * transaction a page with the cursor in it, and at the end of a complete pass the removal of
 * what the carrier no longer has — held when it would take too much (§7.5).
 *
 * A page is repeatable: its rows are keyed by the carrier's ids, so a page tried again writes the
 * same rows. A run that fails part-way removes nothing: the checkout keeps working on the last
 * complete directory plus whatever pages got through (decision 12). The shop's own points
 * (`carrier = store`) are never touched.
 */
final class Synchronizer
{
    public const string POINTS_GROUP = 'delivery-points';

    public const float DEFAULT_MAX_REMOVAL_SHARE = 0.1;

    /** The warning of a streets pass: localities where the carrier has a courier and no street (§8.2). */
    public const string NO_STREETS = 'no_streets';

    /** The journal keeps this many runs of each carrier's directory (§5). */
    public const int KEEP_RUNS = 100;

    public function __construct(
        private readonly Directories $directories,
        private readonly LocalityPages $localities,
        private readonly PointPages $points,
        private readonly StreetPages $streets,
        private readonly CacheGroups $groups,
        private readonly Config $config,
    ) {}

    /**
     * Opens a run. A carrier that is off, or a directory it does not have, is refused; a run of
     * the directory going on is too, unless it has been silent so long that it is dead.
     *
     * @throws SyncAlreadyRunning
     * @throws DeliveryException
     */
    public function start(string $carrier, Directory $directory, Trigger $trigger): DeliverySyncRun
    {
        if ($carrier === Carriers::STORE) {
            throw new DeliveryException('The shop\'s own points are kept in the panel, not synced.');
        }

        $this->directories->schedule($carrier, $directory);

        if (! $this->directories->enabled($carrier)) {
            throw new DeliveryException("Delivery carrier {$carrier} is off: switch it on before syncing.");
        }

        return DB::transaction(function () use ($carrier, $directory, $trigger): DeliverySyncRun {
            $running = DeliverySyncRun::query()->of($carrier, $directory)
                ->where('status', RunStatus::Running->value)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($running !== null) {
                if (! $this->dead($running)) {
                    throw new SyncAlreadyRunning($running);
                }

                $this->close($running, SyncFailure::Interrupted);
            }

            return DeliverySyncRun::query()->create([
                'carrier' => $carrier,
                'directory' => $directory,
                'status' => RunStatus::Running,
                'trigger' => $trigger,
                'started_at' => now(),
            ]);
        });
    }

    /** The run of the directory going on now, if any — «already running, page N» (§7.7). */
    public function running(string $carrier, Directory $directory): ?DeliverySyncRun
    {
        $run = DeliverySyncRun::query()->of($carrier, $directory)
            ->where('status', RunStatus::Running->value)
            ->orderByDesc('id')
            ->first();

        return $run === null || $this->dead($run) ? null : $run;
    }

    /**
     * One page from the run's cursor, written in one transaction with the run's cursor and
     * counters. At the last page the pass is finished. True while pages are left.
     *
     * @throws CarrierUnavailable the carrier failed; the page is tried again from the same cursor
     * @throws InvalidRecord a record broke the contract
     */
    public function page(DeliverySyncRun $run): bool
    {
        if (! $run->isRunning()) {
            return false;
        }

        $schedule = $this->directories->schedule($run->carrier, $run->directory);
        $page = $this->directories->carrier($run->carrier)->pull($run->directory, $run->cursor);

        if (count($page->records) > max($schedule->pageSize, 1) * 2) {
            throw new InvalidRecord(sprintf('A page of %d records is over twice the page size of %d.', count($page->records), $schedule->pageSize));
        }

        DB::transaction(function () use ($run, $page): void {
            $result = match ($run->directory) {
                Directory::Localities => $this->localities->write($run->carrier, $run->id, array_values($page->records)),
                Directory::Points => $this->points->write($run->carrier, $run->id, array_values($page->records)),
                Directory::Streets => $this->streets->write($run->carrier, $run->id, array_values($page->records)),
            };

            $warnings = $run->warnings ?? [];

            foreach ([...$page->skipped, ...$result->skipped] as $why => $ids) {
                $warnings['skipped'][$why]['count'] = ($warnings['skipped'][$why]['count'] ?? 0) + count($ids);
                $warnings['skipped'][$why]['ids'] = array_slice([...($warnings['skipped'][$why]['ids'] ?? []), ...$ids], 0, 50);
            }

            $run->forceFill([
                'cursor' => $page->next,
                'pages' => $run->pages + 1,
                'seen' => $run->seen + $result->seen,
                'created' => $run->created + $result->created,
                'updated' => $run->updated + $result->updated,
                'queued' => $run->queued + $result->queued,
                'warnings' => $warnings === [] ? null : $warnings,
            ])->save();
        });

        if ($run->directory === Directory::Points) {
            $this->groups->bump(self::POINTS_GROUP);
        }

        if ($page->next !== null) {
            return true;
        }

        $this->finish($run);

        return false;
    }

    /**
     * Pages until the run ends or `$seconds` are spent; the carrier's failure is let through, for
     * the queue to try the page again later.
     *
     * @return bool true while pages are left
     */
    public function pages(DeliverySyncRun $run, ?float $seconds = null): bool
    {
        $until = $seconds === null ? null : microtime(true) + $seconds;

        while ($this->page($run)) {
            if ($until !== null && microtime(true) >= $until) {
                return true;
            }
        }

        return false;
    }

    /**
     * A run in this process from start to end: a page the carrier failed is tried again from the
     * same cursor after a pause (`webx-delivery.sync.backoff`), up to `sync.tries` times; then the
     * run fails. What the command does without `--queue`.
     *
     * @throws SyncAlreadyRunning
     * @throws DeliveryException
     */
    public function run(string $carrier, Directory $directory, Trigger $trigger = Trigger::Command): DeliverySyncRun
    {
        $run = $this->start($carrier, $directory, $trigger);
        $attempt = 0;

        while (true) {
            try {
                if (! $this->page($run)) {
                    return $run->refresh();
                }

                $attempt = 0;
            } catch (CarrierUnavailable $failure) {
                if (++$attempt >= $this->tries()) {
                    $this->fail($run, $failure);

                    return $run->refresh();
                }

                Sleep::for($this->backoff()[$attempt - 1] ?? 0)->seconds();
            } catch (Throwable $failure) {
                $this->fail($run, $failure);

                return $run->refresh();
            }
        }
    }

    /**
     * The run failed: its words in the journal, the failure itself in the log. Nothing is
     * removed — the last complete directory stays in force.
     */
    public function fail(DeliverySyncRun $run, Throwable $failure): void
    {
        report($failure);

        $this->close($run->refresh(), match (true) {
            $failure instanceof CarrierUnavailable => $failure->failure,
            $failure instanceof InvalidRecord => SyncFailure::BadResponse,
            default => SyncFailure::Failed,
        });
    }

    /**
     * «Confirm the removal» of a held pass (§7.5): removes what the carrier no longer has, past
     * the threshold. Only the latest run of the directory can be confirmed — a newer pass has
     * seen other records, and the held one's picture is out of date.
     */
    public function confirm(DeliverySyncRun $run): DeliverySyncRun
    {
        if ($run->status !== RunStatus::Held) {
            throw new DeliveryException("Run {$run->id} is not held: there is nothing to confirm.");
        }

        if (DeliverySyncRun::query()->of($run->carrier, $run->directory)->where('id', '>', $run->id)->exists()) {
            throw new DeliveryException("Run {$run->id} is not the latest of {$run->carrier} {$run->directory->value}: run the sync again and confirm that one.");
        }

        DB::transaction(function () use ($run): void {
            $removed = $this->remove($run);
            $warnings = $run->warnings ?? [];
            $warnings['confirmed_at'] = now()->toIso8601String();

            $run->forceFill(['status' => RunStatus::Succeeded, 'removed' => $removed, 'warnings' => $warnings])->save();
        });

        $this->after($run);

        return $run;
    }

    /**
     * What confirming a held run would remove (§7.5): how many records in force the run did not
     * see, and the first of them by id — what `delivery_sync_confirm` shows on a dry run.
     *
     * @return array{count: int, first: list<array{id: int, external_id: string}>}
     */
    public function vanishing(DeliverySyncRun $run, int $limit = 50): array
    {
        return [
            'count' => $this->vanished($run)->count(),
            'first' => $this->vanished($run)->orderBy('id')->limit($limit)->get(['id', 'external_id'])
                ->map(static fn (object $row): array => ['id' => (int) $row->id, 'external_id' => (string) $row->external_id])
                ->values()
                ->all(),
        ];
    }

    /** @return positive-int */
    public function tries(): int
    {
        return max(1, (int) $this->config->get('webx-delivery.sync.tries', 3));
    }

    /** @return list<int> seconds before the second, third… try of a page (§7.6) */
    public function backoff(): array
    {
        $backoff = $this->config->get('webx-delivery.sync.backoff', [60, 300, 900]);

        return array_values(array_map('intval', is_array($backoff) ? $backoff : [60, 300, 900]));
    }

    // The end of a pass

    /**
     * A complete pass: what the carrier did not return this time is removed — unless it is more
     * than the threshold of what it had, then the run is held and nothing goes (§7.5).
     */
    private function finish(DeliverySyncRun $run): void
    {
        $active = $this->rows($run)->whereNull('removed_at')->count();
        $vanished = $this->vanished($run)->count();
        $share = $this->config->get('webx-delivery.sync.max_removal_share', self::DEFAULT_MAX_REMOVAL_SHARE);
        $share = is_numeric($share) ? (float) $share : self::DEFAULT_MAX_REMOVAL_SHARE;

        if ($vanished > 0 && $active > 0 && $vanished / $active > $share) {
            $warnings = $run->warnings ?? [];
            $warnings['held'] = $vanished;
            $run->forceFill(['status' => RunStatus::Held, 'warnings' => $warnings, 'finished_at' => now()])->save();
            $this->after($run);

            return;
        }

        DB::transaction(function () use ($run, $vanished): void {
            $removed = $vanished > 0 ? $this->remove($run) : 0;
            $run->forceFill(['status' => RunStatus::Succeeded, 'removed' => $removed, 'finished_at' => now()])->save();
        });

        $this->after($run);
    }

    /**
     * Marks what the run did not see as removed. Removed, not deleted: carts and shipments still
     * name them (§7.5). The points and streets of a removed locality go with it, and its count of
     * streets with them: a locality that comes back has none in force until the next streets pass.
     */
    private function remove(DeliverySyncRun $run): int
    {
        $now = now();

        if ($run->directory === Directory::Localities) {
            DB::table('delivery_points')
                ->where('carrier', $run->carrier)
                ->whereNull('removed_at')
                ->whereIn('carrier_locality_id', $this->vanished($run)->select('id'))
                ->update(['removed_at' => $now, 'updated_at' => $now]);

            DB::table('delivery_streets')
                ->where('carrier', $run->carrier)
                ->whereNull('removed_at')
                ->whereIn('carrier_locality_id', $this->vanished($run)->select('id'))
                ->update(['removed_at' => $now, 'updated_at' => $now]);

            return $this->vanished($run)->update(['removed_at' => $now, 'streets' => 0, 'updated_at' => $now]);
        }

        return $this->vanished($run)->update(['removed_at' => $now, 'updated_at' => $now]);
    }

    /**
     * After the pass: the points the checkout reads, the order of the suggestions, where a locality
     * lies, the streets a courier has in each locality, the journal's length.
     */
    private function after(DeliverySyncRun $run): void
    {
        if ($run->directory === Directory::Points) {
            $this->weigh($run->carrier);
            $this->locate($run->carrier);
        }

        if ($run->directory === Directory::Streets) {
            $this->countStreets($run);
        }

        // Streets are not cached: their suggestions are one query by the index (§17).
        if ($run->directory !== Directory::Streets) {
            $this->groups->bump(self::POINTS_GROUP);
        }

        $this->prune($run);
    }

    /**
     * The order of the suggestions (§4.1): a locality weighs its type and one for each active
     * point of every carrier in it — one `UPDATE` for the localities this carrier is bound to.
     * A weight an administrator set by hand stays (decision 42).
     */
    private function weigh(string $carrier): void
    {
        // The kinds are the closed list of the enum: written into the statement, not bound.
        $kinds = 'case kind';

        foreach (LocalityKind::cases() as $kind) {
            $kinds .= " when '{$kind->value}' then ".$kind->weight();
        }

        $kinds .= ' else 0 end';

        DB::table('geo_localities')
            ->whereIn('id', DB::table('delivery_carrier_localities')->select('locality_id')->where('carrier', $carrier)->whereNotNull('locality_id'))
            ->where(static function (Builder $query): void {
                $query->whereNull('manual_fields')->orWhere('manual_fields', 'not like', '%"weight"%');
            })
            ->update([
                'weight' => DB::raw("({$kinds}) + (select count(*) from delivery_points p where p.locality_id = geo_localities.id and p.active = 1 and p.removed_at is null)"),
            ]);
    }

    /**
     * Where a locality lies, from its points (§7.3): a carrier whose towns come without coordinates
     * — Nova Poshta's `getCities` has none — still gives them to its points, and the matcher needs
     * them to tell namesakes of one region apart (`auto_coords`, §4.3). The middle of the carrier's
     * points in force with coordinates — one `UPDATE` for the localities this carrier has such
     * points in. Only a locality without coordinates is filled, and neither one made by hand nor one
     * whose coordinates a person set or cleared (decision 42): as with the leading carrier's fill-in,
     * a value is never replaced and what a person keeps is left to them.
     */
    private function locate(string $carrier): void
    {
        // Points in force with coordinates; 0, 0 is a coordinate nobody filled in, not a place.
        $located = 'p.carrier = ? and p.removed_at is null and p.lat is not null and p.lng is not null and not (p.lat = 0 and p.lng = 0)';
        $middle = static fn (string $column): string => "(select round(avg(p.{$column}), 6) from delivery_points p where p.locality_id = geo_localities.id and {$located})";

        // Plain SQL: a value with a binding in its subquery — the same on MariaDB and sqlite.
        DB::update(
            'update geo_localities set lat = '.$middle('lat').', lng = '.$middle('lng')
            .' where lat is null and lng is null and created_by <> ?'
            .' and (manual_fields is null or (manual_fields not like \'%"lat"%\' and manual_fields not like \'%"lng"%\'))'
            ." and id in (select p.locality_id from delivery_points p where p.locality_id is not null and {$located})",
            [$carrier, $carrier, GeoLocality::MANUAL, $carrier],
        );
    }

    /**
     * The streets in force of each of the carrier's localities, by one `UPDATE` (§7.3): `0` turns
     * its courier there off (§8.2). A locality where the carrier says it goes to doors and has no
     * street is the carrier's directory gone wrong — the run says how many in its warnings.
     */
    private function countStreets(DeliverySyncRun $run): void
    {
        DB::table('delivery_carrier_localities')
            ->where('carrier', $run->carrier)
            ->update([
                'streets' => DB::raw('(select count(*) from delivery_streets s where s.carrier_locality_id = delivery_carrier_localities.id and s.removed_at is null)'),
            ]);

        $without = DB::table('delivery_carrier_localities')
            ->where('carrier', $run->carrier)
            ->whereNull('removed_at')
            ->where('streets', 0)
            ->where('features', 'like', '%"courier"%');

        $count = (clone $without)->count();
        $warnings = $run->warnings ?? [];
        unset($warnings[self::NO_STREETS]);

        if ($count > 0) {
            $warnings[self::NO_STREETS] = [
                'count' => $count,
                'ids' => $without->orderBy('id')->limit(50)->pluck('external_id')->map(static fn (mixed $id): string => (string) $id)->all(),
            ];
        }

        $run->forceFill(['warnings' => $warnings === [] ? null : $warnings])->save();
    }

    /** The journal keeps the last {@see KEEP_RUNS} runs of a carrier's directory. */
    private function prune(DeliverySyncRun $run): void
    {
        $oldest = DeliverySyncRun::query()->of($run->carrier, $run->directory)
            ->orderByDesc('id')
            ->skip(self::KEEP_RUNS - 1)
            ->value('id');

        if ($oldest !== null) {
            DeliverySyncRun::query()->of($run->carrier, $run->directory)->where('id', '<', $oldest)->delete();
        }
    }

    /** The directory's rows of the carrier. */
    private function rows(DeliverySyncRun $run): Builder
    {
        return DB::table(match ($run->directory) {
            Directory::Localities => 'delivery_carrier_localities',
            Directory::Points => 'delivery_points',
            Directory::Streets => 'delivery_streets',
        })->where('carrier', $run->carrier);
    }

    /** The rows still in force that the run did not see. */
    private function vanished(DeliverySyncRun $run): Builder
    {
        return $this->rows($run)
            ->whereNull('removed_at')
            ->where(static function (Builder $query) use ($run): void {
                $query->whereNull('seen_run_id')->orWhere('seen_run_id', '<>', $run->id);
            });
    }

    private function close(DeliverySyncRun $run, SyncFailure $failure): void
    {
        $run->forceFill([
            'status' => RunStatus::Failed,
            'error' => mb_substr($failure->words(), 0, 500, 'UTF-8'),
            'finished_at' => now(),
        ])->save();
    }

    /** A running run silent past `sync.stuck_after_minutes` — its worker is gone. */
    private function dead(DeliverySyncRun $run): bool
    {
        $minutes = max(1, (int) $this->config->get('webx-delivery.sync.stuck_after_minutes', 360));

        return $run->started_at instanceof Carbon && $run->started_at->lt(now()->subMinutes($minutes));
    }
}
