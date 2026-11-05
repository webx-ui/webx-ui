<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use WebxUi\Admin\Support\PrivateDisk;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\StorefrontCache;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\Templates\Compiled;
use WebxUi\Templates\Escapes;
use WebxUi\Templates\Template;
use WebxUi\Templates\TemplateError;

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

    private const DIRECTORY = 'catalog-feeds';

    private const JOIN = 'join';

    private const CHECK = 'check';

    private const PUBLISH = 'publish';

    public function __construct(
        private readonly FeedSelection $selection,
        private readonly FeedVariables $feedVariables,
        private readonly ProductVariables $variables,
        private readonly FeedContext $context,
        private readonly FeedFileCheck $check,
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

        if ($build->status === FeedBuild::QUEUED) {
            $build->forceFill([
                'status' => FeedBuild::RUNNING,
                'started_at' => Carbon::now(),
                'progress_at' => Carbon::now(),
                'excluded' => $this->selection->excluded($feed),
            ])->save();
        }

        $template = (string) $feed->item;

        if (trim($template) === '') {
            throw FeedFailure::of('no-item');
        }

        $item = $this->compile($template, $feed);
        $names = $this->names($feed, $template);
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

        /** @var Collection<int, Product> $products */
        $products = (clone $selected)
            ->with('images')
            ->where('catalog_products.id', '>', $from)
            ->orderBy('catalog_products.id')
            ->limit($chunk)
            ->get();

        if ($products->isEmpty()) {
            return false;
        }

        $written = (int) $build->written;
        $filtered = 0;
        $reasons = [];
        $rejects = [];
        $warnings = [];
        $categories = [];
        $kept = new Collection;

        foreach ($products as $product) {
            $reason = FeedChecks::reason($product, $feed->locale);

            if ($reason === null) {
                $kept->push($product);

                continue;
            }

            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            $rejects[] = ['build_id' => $build->id, 'product_id' => $product->id, 'reason' => $reason];
        }

        $rows = $kept->isEmpty() ? [] : $this->variables->values($kept, $feed->locale, $names, $this->feedVariables->overrides($feed));
        $onlyInStock = in_array('is_purchasable', $names, true) && (bool) ($feed->filter['only_in_stock'] ?? false);

        foreach ($kept as $product) {
            $id = (int) $product->id;
            $row = $rows[$id];

            if ($onlyInStock && $row['is_purchasable'] === '') {
                $filtered++;

                continue;
            }

            $text = $item->render($row);

            if ($feed->format === 'json') {
                $this->check->jsonItem($text, $id);
            }

            fwrite($body, ($written > 0 ? $feed->separator : '').$text);
            $written++;

            foreach (FeedChecks::warnings($product, $row, $feed->locale) as $warning) {
                $warnings[$warning] = ($warnings[$warning] ?? 0) + 1;
            }

            if ($product->category_id !== null) {
                $categories[(int) $product->category_id] = true;
            }
        }

        fflush($body);
        $bytes = (int) ftell($body);

        if ($bytes > (int) $this->config->get('webx-catalog-feeds.max_bytes')) {
            throw FeedFailure::of('too-large');
        }

        $this->record($build, $products, $written, $filtered, $bytes, $reasons, $rejects, $warnings, array_keys($categories));
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
        $names = $this->frameNames($feed);
        $values = $this->context->values($feed, $build, $names);
        $head = $this->frame((string) $feed->head, $feed, $values);
        $foot = $this->frame((string) $feed->foot, $feed, $values);

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
        $target = self::DIRECTORY.'/'.$feed->id.'/'.$build->id.'.'.Feed::FORMATS[$feed->format];
        $stream = fopen($path, 'rb') ?: throw new RuntimeException('The file of a feed vanished before it was kept.');

        try {
            if ($this->disk()->writeStream($target, $stream) === false) {
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

            // Not through the model: a build is no edit of the feed, and the journal stays out of it.
            Feed::query()->whereKey($feed->id)->update([
                'current_build_id' => $build->id,
                'built_generation' => $build->generation,
                'dirty_since' => null,
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

    /** Always on this machine, whatever the feed's disk: a disk in the cloud cannot be appended to. */
    private function temporary(FeedBuild $build, string $kind): string
    {
        $directory = storage_path('app/'.self::DIRECTORY);

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        return $directory.'/'.$build->id.'.'.$kind;
    }

    private function disk(): Filesystem
    {
        $name = (string) ($this->config->get('webx-catalog-feeds.disk') ?: 'local');

        // What guards a feed is its key: on a public disk the file is there for anyone.
        PrivateDisk::fromConfig()->assert($name, 'webx-catalog-feeds.disk');

        return Storage::disk($name);
    }

    /**
     * @return list<string>
     */
    private function names(Feed $feed, string $template): array
    {
        try {
            $names = $this->feedVariables->used($template);
        } catch (TemplateError $error) {
            throw FeedFailure::of('template', ['reason' => $error->words()]);
        }

        foreach (FeedChecks::COMPANIONS as $name => $companion) {
            if (in_array($name, $names, true)) {
                $names[] = $companion;
            }
        }

        if ((bool) ($feed->filter['only_in_stock'] ?? false) && isset($this->variables->all()['is_purchasable'])) {
            $names[] = 'is_purchasable';
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function frameNames(Feed $feed): array
    {
        try {
            return array_column([...Template::usages((string) $feed->head), ...Template::usages((string) $feed->foot)], 'name');
        } catch (TemplateError $error) {
            throw FeedFailure::of('template', ['reason' => $error->words()]);
        }
    }

    /**
     * @param  array<string, string|list<array<string, string>>>  $values
     */
    private function frame(string $template, Feed $feed, array $values): string
    {
        return $template === '' ? '' : $this->compile($template, $feed)->render($values);
    }

    private function compile(string $template, Feed $feed): Compiled
    {
        try {
            return Template::compile($template, Escapes::for($feed->format, ['delimiter' => FeedFileCheck::delimiter($feed)]));
        } catch (TemplateError $error) {
            throw FeedFailure::of('template', ['reason' => $error->words()]);
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
