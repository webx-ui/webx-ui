<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Delivery;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use WebxUi\Catalog\Exchange\ExchangeProfile;
use WebxUi\Catalog\Exchange\ExchangeRun;
use WebxUi\Catalog\Storefront\StorefrontCache;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;

/**
 * Which feeds are due a build (§7.3 of the feeds spec): only decides and queues, once a minute.
 *
 * The signal of a change is the catalogue's generation, which every way of writing moves. An import
 * moves it on each batch, so a change alone never starts a build: the catalogue has to fall quiet,
 * an import has to end, and neither is waited for past `max_wait`. `min_interval` holds in any case.
 *
 * The last build counts whatever came of it: a build that keeps failing is tried once a period and
 * not once a minute.
 */
final class FeedWatch
{
    /** Without the templates: a look a minute has no use for them. */
    private const COLUMNS = [
        'id', 'schedule', 'schedule_at', 'rebuild_on_change', 'is_active', 'built_generation',
        'seen_generation', 'dirty_since', 'first_dirty_at',
    ];

    private ?bool $importing = null;

    public function __construct(
        private readonly FeedBuilder $builder,
        private readonly StorefrontCache $cache,
        private readonly Config $config,
    ) {}

    /** @return int how many feeds were sent to build */
    public function tick(): int
    {
        $this->importing = null;
        $now = Carbon::now();
        $generation = (string) $this->cache->generation();
        $started = 0;

        $feeds = Feed::query()
            ->select(self::COLUMNS)
            ->with('lastBuild:id,feed_id,queued_at')
            ->where('is_active', true)
            ->where(static fn ($query) => $query->where('schedule', '!=', 'manual')->orWhere('rebuild_on_change', true))
            ->orderBy('id')
            ->get();

        foreach ($feeds as $feed) {
            $last = $feed->lastBuild?->queued_at;

            if ($feed->rebuild_on_change) {
                $this->notice($feed, $generation, $now);
            }

            $trigger = match (true) {
                $this->scheduled($feed, $last, $now) => FeedBuild::SCHEDULE,
                $feed->rebuild_on_change && $this->settled($feed, $last, $now) => FeedBuild::CHANGE,
                default => null,
            };

            if ($trigger !== null && $this->builder->start($feed, $trigger)?->wasRecentlyCreated) {
                $started++;
            }
        }

        return $started;
    }

    private function scheduled(Feed $feed, ?Carbon $last, Carbon $now): bool
    {
        if ($feed->schedule === 'daily') {
            return $last === null || $last->lt($this->moment($feed, $now));
        }

        $period = Feed::PERIODS[$feed->schedule] ?? null;

        return $period !== null && self::past($last, $period, $now);
    }

    /** The last time the clock of a daily feed struck, today's or, before it, yesterday's. */
    private function moment(Feed $feed, Carbon $now): Carbon
    {
        $moment = $now->copy()->setTimeFromTimeString($feed->schedule_at ?? '00:00');

        return $moment->gt($now) ? $moment->subDay() : $moment;
    }

    /** Written past the model: the watcher's notes are no edit of the feed, and no save of it. */
    private function notice(Feed $feed, string $generation, Carbon $now): void
    {
        if ($generation === $feed->built_generation) {
            if ($feed->dirty_since !== null) {
                $this->note($feed, ['dirty_since' => null, 'first_dirty_at' => null, 'seen_generation' => null]);
            }

            return;
        }

        if ($feed->dirty_since !== null && $generation === $feed->seen_generation) {
            return;
        }

        $this->note($feed, [
            'dirty_since' => $now,
            'first_dirty_at' => $feed->first_dirty_at ?? $now,
            'seen_generation' => $generation,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function note(Feed $feed, array $values): void
    {
        Feed::query()->whereKey($feed->id)->toBase()->update($values);

        $feed->forceFill($values)->syncOriginal();
    }

    private function settled(Feed $feed, ?Carbon $last, Carbon $now): bool
    {
        if ($feed->dirty_since === null || ! self::past($last, $this->minutes('min_interval', 60), $now)) {
            return false;
        }

        if ($feed->first_dirty_at?->copy()->addMinutes($this->minutes('max_wait', 360))->lte($now)) {
            return true;
        }

        return ! $this->importing() && $feed->dirty_since->copy()->addMinutes($this->minutes('quiet_minutes', 10))->lte($now);
    }

    /** A rehearsal changes nothing, so a build does not wait for it. */
    private function importing(): bool
    {
        return $this->importing ??= ExchangeRun::query()
            ->where('direction', ExchangeProfile::IMPORT)
            ->where('dry_run', false)
            ->where('status', ExchangeRun::RUNNING)
            ->exists();
    }

    /** By the minute: a watcher that wakes a second late must not put a period off by a minute. */
    private static function past(?Carbon $last, int $minutes, Carbon $now): bool
    {
        return $last === null || $last->copy()->startOfMinute()->addMinutes($minutes)->lte($now);
    }

    private function minutes(string $key, int $default): int
    {
        return (int) $this->config->get('webx-catalog-feeds.'.$key, $default);
    }
}
