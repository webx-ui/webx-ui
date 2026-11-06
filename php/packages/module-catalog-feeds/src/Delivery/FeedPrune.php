<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Delivery;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;

/**
 * What the feeds no longer need (§7.6 of the feeds spec): files past `keep_files`, the journal past
 * `keep_builds_days`, and what a dead build or a deleted feed left behind.
 *
 * Kept whatever their age: the build being served and the `keep_files` before it, for whoever began
 * to read one of them when the feed moved on. A build under way is never touched. Each listing is
 * made before the builds are read, so a build that begins in between is not in it.
 */
final class FeedPrune
{
    public function __construct(
        private readonly FeedFiles $files,
        private readonly Config $config,
    ) {}

    /** @return array{files: int, builds: int} */
    public function run(): array
    {
        $files = 0;
        $builds = 0;
        $listed = $this->files->disk()->directories(FeedFiles::DIRECTORY);
        $feeds = Feed::query()->get(['id', 'current_build_id']);

        foreach ($feeds as $feed) {
            [$removed, $rows] = $this->feed($feed);
            $files += $removed;
            $builds += $rows;
        }

        $this->strays($listed, $feeds->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all());
        $this->temporaries();

        return ['files' => $files, 'builds' => $builds];
    }

    /** @return array{int, int} files and rows of the journal removed */
    private function feed(Feed $feed): array
    {
        $disk = $this->files->disk();
        $listed = $disk->files($this->files->directory((int) $feed->id));

        /** @var Collection<int, FeedBuild> $done */
        $done = FeedBuild::query()
            ->where('feed_id', $feed->id)
            ->where('status', FeedBuild::DONE)
            ->whereNotNull('path')
            ->orderByDesc('id')
            ->get(['id', 'path']);

        $current = $done->where('id', $feed->current_build_id);
        $kept = $current->concat($done->where('id', '!=', $feed->current_build_id)->take($this->keep()));
        $paths = $kept->pluck('path')->all();
        $going = $this->going((int) $feed->id);
        $removed = 0;

        foreach ($listed as $file) {
            if (! in_array($file, $paths, true) && ! in_array((int) pathinfo($file, PATHINFO_FILENAME), $going, true)) {
                $disk->delete($file);
                $removed++;
            }
        }

        FeedBuild::query()->whereIn('id', $done->pluck('id')->diff($kept->pluck('id')))->update(['path' => null]);

        $rows = FeedBuild::query()
            ->where('feed_id', $feed->id)
            ->whereIn('status', [FeedBuild::DONE, FeedBuild::FAILED])
            ->where('finished_at', '<', Carbon::now()->subDays((int) $this->config->get('webx-catalog-feeds.keep_builds_days', 30)))
            ->whereNotIn('id', $kept->pluck('id'))
            ->delete();

        return [$removed, (int) $rows];
    }

    /**
     * The directories of feeds that are gone, which a restored snapshot leaves behind.
     *
     * @param  list<string>  $listed
     * @param  list<string>  $feeds
     */
    private function strays(array $listed, array $feeds): void
    {
        foreach ($listed as $directory) {
            $name = basename($directory);

            if (ctype_digit($name) && ! in_array($name, $feeds, true)) {
                $this->files->disk()->deleteDirectory($directory);
            }
        }
    }

    /** What a build killed outright, or one whose feed was deleted, left on this machine. */
    private function temporaries(): void
    {
        $listed = glob($this->files->temporaryDirectory().'/*.{body,file}', GLOB_BRACE) ?: [];
        $going = $this->going();

        foreach ($listed as $path) {
            if (! in_array((int) pathinfo($path, PATHINFO_FILENAME), $going, true)) {
                @unlink($path);
            }
        }
    }

    /** @return list<int> */
    private function going(?int $feed = null): array
    {
        return FeedBuild::query()
            ->whereIn('status', [FeedBuild::QUEUED, FeedBuild::RUNNING])
            ->when($feed !== null, static fn ($query) => $query->where('feed_id', $feed))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function keep(): int
    {
        return max(0, (int) $this->config->get('webx-catalog-feeds.keep_files', 2));
    }
}
