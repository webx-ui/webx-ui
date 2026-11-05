<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\StorefrontCache;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\CatalogFeeds\Delivery\FeedFiles;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\Templates\Compiled;

/**
 * A build of a feed (§7 of the feeds spec): pages of products by the cursor, each written to a
 * temporary file on this machine, then the head, the body and the foot joined, checked and put on
 * the feed's disk. The head comes last because it speaks about what the pages found.
 *
 * **Every step after the pages is a job of its own.** A worker kills a job at its timeout, and on a
 * file near `max_bytes` the end of a build does not fit behind a full budget of pages, nor all of
 * it in one job. The step reached is kept in the build's `state`, so a repeated job repeats a step.
 *
 * **A repeated page changes nothing.** The cursor, the counters, the journal's rows and the length
 * of the body are written together, and a job starts by cutting the body to that length, so what a
 * page that died had written is gone and the page is simply done again.
 *
 * The temporary files live here, so a build that moves to a worker on another machine does not find
 * its body; a site with several workers keeps them on one (`onOneServer` is the same promise).
 */
final class FeedBuilder
{
    use Localizable;

    private const JOIN = 'join';

    private const CHECK = 'check';

    private const PUBLISH = 'publish';

    public function __construct(
        private readonly FeedSelection $selection,
        private readonly FeedRender $render,
        private readonly FeedFileCheck $check,
        private readonly FeedFiles $files,
        private readonly StorefrontCache $cache,
        private readonly Dispatcher $bus,
        private readonly Config $config,
    ) {}

    /**
     * Queue a build, one a feed at a time: the one under way is the answer while it lives.
     * Null for a feed switched off, which is not built.
     */
    public function start(Feed $feed, string $trigger = FeedBuild::MANUAL): ?FeedBuild
    {
        if (! $feed->is_active) {
            return null;
        }

        $build = DB::transaction(function () use ($feed, $trigger): FeedBuild {
            Feed::query()->lockForUpdate()->findOrFail($feed->id);

            $going = FeedBuild::query()
                ->where('feed_id', $feed->id)
                ->whereIn('status', [FeedBuild::QUEUED, FeedBuild::RUNNING])
                ->orderByDesc('id')
                ->first();

            if ($going instanceof FeedBuild) {
                if (! $this->stale($going)) {
                    return $going;
                }

                $this->close($going, (string) __('webx-catalog-feeds::errors.stale'));
            }

            return FeedBuild::query()->create([
                'feed_id' => $feed->id,
                'status' => FeedBuild::QUEUED,
                'trigger' => $trigger,
                'queued_at' => Carbon::now(),
                // Read before the first page: what changes during a build asks for the next one.
                'generation' => (string) $this->cache->generation(),
            ]);
        });

        if ($build->wasRecentlyCreated) {
            $this->bus->dispatch(new BuildFeed((int) $build->id));
        }

        return $build;
    }

    /**
     * One stretch of a build: pages for `$seconds`, or the next step of its end.
     *
     * @return bool whether there is more for the next job
     */
    public function work(int $buildId, float $seconds): bool
    {
        $build = FeedBuild::query()->find($buildId);
        $feed = $build?->feed;

        if (! $build instanceof FeedBuild || ! $feed instanceof Feed || $build->finished()) {
            return false;
        }

        try {
            return $this->withLocale($feed->locale, fn (): bool => match ($build->state['step'] ?? null) {
                self::JOIN => $this->join($build, $feed),
                self::CHECK => $this->check($build, $feed, $seconds),
                self::PUBLISH => $this->publish($build, $feed),
                default => $this->pages($build, $feed, $seconds),
            });
        } catch (FeedFailure $failure) {
            $this->fail($buildId, $failure->getMessage());

            return false;
        }
    }

    /** The end of a build that did not make it; the file served stays (§7.2). */
    public function fail(int $buildId, string $error): void
    {
        $build = FeedBuild::query()->find($buildId);

        if ($build instanceof FeedBuild && ! $build->finished()) {
            $this->close($build, $error);
        }
    }

    private function pages(FeedBuild $build, Feed $feed, float $seconds): bool
    {
        $until = microtime(true) + $seconds;

        $this->sameCurrency($feed);

        if ($build->status === FeedBuild::QUEUED) {
            $build->forceFill([
                'status' => FeedBuild::RUNNING,
                'started_at' => Carbon::now(),
                'progress_at' => Carbon::now(),
                'excluded' => $this->selection->excluded($feed),
            ])->save();
        }

        $item = $this->render->item($feed);
        $names = $this->render->names($feed);
        $selected = $this->selection->query($feed);
        $body = $this->body($build);

        try {
            do {
                $more = $this->page($build, $feed, $selected, $item, $names, $body);
            } while ($more && microtime(true) < $until);
        } finally {
            fclose($body);
        }

        return $more || $this->step($build->refresh(), self::JOIN);
    }

    /**
     * @param  Builder<Product>  $selected
     * @param  list<string>  $names
     * @param  resource  $body
     * @return bool whether a next page may be there
     */
    private function page(FeedBuild $build, Feed $feed, Builder $selected, Compiled $item, array $names, $body): bool
    {
        $chunk = max(1, (int) $this->config->get('webx-catalog-feeds.chunk', 500));
        $from = (int) $build->cursor;

        $products = $this->render->products($selected, $from, $chunk);

        if ($products->isEmpty()) {
            return false;
        }

        $written = (int) $build->written;

        $page = $this->render->page($feed, $products, $item, $names, function (string $text, int $id) use ($feed, $body, &$written): void {
            if ($feed->format === 'json') {
                $this->check->jsonItem($text, $id);
            }

            fwrite($body, ($written > 0 ? $feed->separator : '').$text);
            $written++;
        });

        fflush($body);
        $bytes = (int) ftell($body);

        if ($bytes > (int) $this->config->get('webx-catalog-feeds.max_bytes')) {
            throw FeedFailure::of('too-large');
        }

        $rejects = [];

        foreach ($page->rejected as $id => $reason) {
            $rejects[] = ['build_id' => $build->id, 'product_id' => $id, 'reason' => $reason];
        }

        $this->record($build, $products, $written, count($page->filtered), $bytes, $page->reasons(), $rejects, $page->warnings, $page->categories);
        RequestMemo::forget();

        return $products->count() === $chunk;
    }

    /**
     * The page, all at once. Read again under the lock: a second worker on the same page waits
     * here and finds the cursor past it.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<string, int>  $reasons
     * @param  list<array{build_id: int, product_id: int, reason: string}>  $rejects
     * @param  array<string, int>  $warnings
     * @param  list<int>  $categories
     */
    private function record(FeedBuild $build, Collection $products, int $written, int $filtered, int $bytes, array $reasons, array $rejects, array $warnings, array $categories): void
    {
        DB::transaction(function () use ($build, $products, $written, $filtered, $bytes, $reasons, $rejects, $warnings, $categories): void {
            $locked = FeedBuild::query()->lockForUpdate()->find($build->id, ['id', 'status', 'cursor']);

            if (! $locked instanceof FeedBuild || $locked->finished() || (int) $locked->cursor !== (int) $build->cursor) {
                throw new RuntimeException("The build [{$build->id}] was moved on by another worker.");
            }

            $room = max(0, (int) $this->config->get('webx-catalog-feeds.max_rejects', 1000) - array_sum($build->rejected ?? []));

            if ($rejects !== [] && $room > 0) {
                DB::table('catalog_feed_build_rejects')->insert(array_slice($rejects, 0, $room));
            }

            $state = $build->state ?? [];
            $state['categories'] = array_values(array_unique([...$state['categories'] ?? [], ...$categories]));

            $build->forceFill([
                'cursor' => (int) $products->last()?->getKey(),
                'body_bytes' => $bytes,
                'selected' => $build->selected + $products->count(),
                'written' => $written,
                'filtered' => $build->filtered + $filtered,
                'rejected' => self::add($build->rejected ?? [], $reasons),
                'warnings' => self::add($build->warnings ?? [], $warnings),
                'state' => $state,
                'progress_at' => Carbon::now(),
            ])->save();
        });
    }

    private function join(FeedBuild $build, Feed $feed): bool
    {
        [$head, $foot] = $this->render->frame($feed, $build);

        $this->check->frame($feed, $head, $foot, (int) $build->written);

        if ($this->write($build, $head, $foot) > (int) $this->config->get('webx-catalog-feeds.max_bytes')) {
            throw FeedFailure::of('too-large');
        }

        return $this->step($build, self::CHECK);
    }

    /** The file, for as long as a job has: a csv of 3 GB takes minutes to read. */
    private function check(FeedBuild $build, Feed $feed, float $seconds): bool
    {
        $from = $build->state['checked'] ?? [];
        $reached = $this->check->file($feed, $this->temporary($build, 'file'), $from, microtime(true) + $seconds);

        return $reached === null
            ? $this->step($build, self::PUBLISH)
            : $this->step($build, self::CHECK, ['checked' => $reached]);
    }

    /**
     * What is left to do goes to the next job.
     *
     * @param  array<string, mixed>  $more
     */
    private function step(FeedBuild $build, string $step, array $more = []): bool
    {
        $build->forceFill([
            'state' => [...$build->state ?? [], ...$more, 'step' => $step],
            'progress_at' => Carbon::now(),
        ])->save();

        return true;
    }

    /** Head, body and foot into one file: a stream, the body may be bigger than the memory. */
    private function write(FeedBuild $build, string $head, string $foot): int
    {
        $file = fopen($this->temporary($build, 'file'), 'wb') ?: throw new RuntimeException('No room for the file of a feed.');
        $body = fopen($this->temporary($build, 'body'), 'rb') ?: throw new RuntimeException('The body of a feed vanished.');

        try {
            fwrite($file, $head);
            stream_copy_to_stream($body, $file);
            fwrite($file, $foot);
            fflush($file);

            return (int) ftell($file);
        } finally {
            fclose($body);
            fclose($file);
        }
    }

    private function publish(FeedBuild $build, Feed $feed): bool
    {
        $path = $this->temporary($build, 'file');
        $bytes = (int) filesize($path);
        $etag = (string) hash_file('sha256', $path);
        $target = $this->files->path((int) $feed->id, (int) $build->id, Feed::FORMATS[$feed->format]);
        $stream = fopen($path, 'rb') ?: throw new RuntimeException('The file of a feed vanished before it was kept.');

        try {
            if ($this->files->disk()->writeStream($target, $stream) === false) {
                throw new RuntimeException("The file [{$target}] could not be written.");
            }
        } finally {
            is_resource($stream) && fclose($stream);
        }

        DB::transaction(function () use ($build, $feed, $target, $bytes, $etag): void {
            $current = $feed->currentBuild()->first();
            $now = Carbon::now();

            $build->forceFill([
                'status' => FeedBuild::DONE,
                'finished_at' => $now,
                'path' => $target,
                'bytes' => $bytes,
                'etag' => $etag,
                // The same bytes keep their date, so a marketplace is not told anything changed.
                'content_at' => $current instanceof FeedBuild && $current->etag === $etag ? $current->content_at : $now,
            ])->save();

            // Past the model: a build is no edit of the feed, for the journal or for `updated_at`.
            Feed::query()->whereKey($feed->id)->toBase()->update([
                'current_build_id' => $build->id,
                'built_generation' => $build->generation,
                'dirty_since' => null,
                'first_dirty_at' => null,
                'seen_generation' => null,
            ]);
        });

        $this->forget($build);

        return false;
    }

    private function close(FeedBuild $build, string $error): void
    {
        $build->forceFill(['status' => FeedBuild::FAILED, 'finished_at' => Carbon::now(), 'error' => $error])->save();

        $this->forget($build);
    }

    private function forget(FeedBuild $build): void
    {
        @unlink($this->temporary($build, 'body'));
        @unlink($this->temporary($build, 'file'));
    }

    private function stale(FeedBuild $build): bool
    {
        $last = $build->progress_at ?? $build->started_at ?? $build->queued_at;
        $minutes = (int) $this->config->get('webx-catalog-feeds.stale_minutes', 30);

        return $last === null || $last->lt(Carbon::now()->subMinutes($minutes));
    }

    /**
     * The body, cut to what was recorded and open at its end.
     *
     * @return resource
     */
    private function body(FeedBuild $build)
    {
        $path = $this->temporary($build, 'body');
        $file = fopen($path, 'c+b') ?: throw new RuntimeException("No room for the body of a feed at [{$path}].");
        $bytes = (int) $build->body_bytes;

        if (! ftruncate($file, $bytes) || fseek($file, $bytes) !== 0) {
            throw new RuntimeException("The body of a feed at [{$path}] cannot be cut.");
        }

        return $file;
    }

    private function temporary(FeedBuild $build, string $kind): string
    {
        $directory = $this->files->temporaryDirectory();

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        return $directory.'/'.$build->id.'.'.$kind;
    }

    /**
     * Prices are already in the site's currency and a feed still says the old one: a person has to
     * look at the template again, and saving it is how they say they did (§8.1).
     */
    private function sameCurrency(Feed $feed): void
    {
        $site = Feed::siteCurrency();

        if ($site !== strtoupper(trim($feed->currency))) {
            throw $site === '' ? FeedFailure::of('currency-unset') : FeedFailure::of('currency', ['currency' => $site]);
        }
    }

    /**
     * @param  array<string, int>  $counters
     * @param  array<string, int>  $more
     * @return array<string, int>
     */
    private static function add(array $counters, array $more): array
    {
        foreach ($more as $key => $count) {
            $counters[$key] = ($counters[$key] ?? 0) + $count;
        }

        return $counters;
    }
}
