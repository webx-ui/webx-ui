<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Taxonomy;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use WebxUi\Admin\Remote\FetchRefused;
use WebxUi\Admin\Remote\RemoteFetcher;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\CatalogFeeds\Models\Taxonomy;

/**
 * A list comes in by address, in the queue, or as a file a person uploaded, and both go through
 * {@see TaxonomyFile} and {@see store()} (§6.2 of the feeds spec). A list that fails to come in
 * leaves the one held as it was.
 */
final class TaxonomyImport
{
    public const UPLOAD_PURPOSE = 'catalog.feed-taxonomy';

    private const FETCH_SECONDS = 60;

    private const BATCH = 500;

    public function __construct(
        private readonly Taxonomies $taxonomies,
        private readonly RemoteFetcher $fetcher,
        private readonly Uploads $uploads,
        private readonly Dispatcher $bus,
        private readonly Config $config,
    ) {}

    /**
     * Queue an import by address, one a list at a time: the one under way is the answer while it lives.
     *
     * @param  string|null  $url  null — the marketplace's own file of this language
     */
    public function queue(string $key, string $locale, ?string $url = null): Taxonomy
    {
        $file = $this->taxonomies->fileLocale($locale);

        [$taxonomy, $fresh] = DB::transaction(function () use ($key, $file): array {
            $taxonomy = Taxonomy::query()->lockForUpdate()->firstOrNew(['key' => $key, 'locale' => $file]);

            if ($taxonomy->going() && ! $this->stale($taxonomy)) {
                return [$taxonomy, false];
            }

            $taxonomy->forceFill(['status' => Taxonomy::QUEUED, 'error' => null, 'queued_at' => Carbon::now()])->save();

            return [$taxonomy, true];
        });

        if ($fresh) {
            $this->bus->dispatch(new ImportTaxonomy((int) $taxonomy->id, $url));
        }

        return $taxonomy;
    }

    /** The queue's half of {@see queue()}. */
    public function run(int $id, ?string $url): void
    {
        $taxonomy = Taxonomy::query()->find($id);

        if (! $taxonomy instanceof Taxonomy || ! $taxonomy->going()) {
            return;
        }

        $taxonomy->forceFill(['status' => Taxonomy::RUNNING])->save();

        $path = tempnam(sys_get_temp_dir(), 'webx-taxonomy-') ?: throw new RuntimeException('No room for the file of a list.');
        $address = $url ?? $this->taxonomies->address($taxonomy->locale);

        try {
            $this->fetch($address, $path);
            $this->store($taxonomy, TaxonomyFile::read($path), $address);
        } catch (FetchRefused $refused) {
            if ($url === null && $refused->status === 404 && $taxonomy->locale !== Taxonomies::FALLBACK) {
                $this->fallBack($taxonomy);

                return;
            }

            $this->fail($id, TaxonomyFailure::of('taxonomy-fetch', ['reason' => $refused->getMessage()])->getMessage());
        } catch (TaxonomyFailure $failure) {
            $this->fail($id, $failure->getMessage());
        } finally {
            @unlink($path);
        }
    }

    /**
     * A file a person uploaded. Read here and not in the queue: a list is a few thousand lines, and
     * a mistake in it is best said to whoever is looking at the form.
     *
     * @throws TaxonomyFailure
     */
    public function upload(string $key, string $locale, string $uploadId, mixed $admin): Taxonomy
    {
        $claimed = $this->uploads->claim($uploadId, self::UPLOAD_PURPOSE, $admin);

        try {
            $file = TaxonomyFile::read($claimed->path);
        } finally {
            $claimed->discard();
        }

        $taxonomy = Taxonomy::query()->firstOrNew(['key' => $key, 'locale' => $this->taxonomies->fileLocale($locale)]);

        return $this->store($taxonomy, $file, mb_substr($claimed->name, 0, 500));
    }

    /** The end of an import that did not make it. */
    public function fail(int $id, string $error): void
    {
        Taxonomy::query()->whereKey($id)->update(['status' => Taxonomy::FAILED, 'error' => $error]);
    }

    /**
     * @throws FetchRefused
     * @throws TaxonomyFailure
     */
    private function fetch(string $address, string $path): void
    {
        $limit = max(1, (int) $this->config->get('webx-catalog-feeds.taxonomy_max_bytes'));
        $fetched = $this->fetcher->download($address, $path, $limit, self::FETCH_SECONDS);

        // The fetcher takes http as well, and a redirect can lead there (§13 asks for https only).
        if (! str_starts_with(strtolower($fetched->url), 'https://')) {
            throw TaxonomyFailure::of('taxonomy-https');
        }
    }

    /**
     * A language with no file of its own reads the English list, and the row that waited for a file
     * has nothing to say any more. One that holds a list a person uploaded keeps it.
     */
    private function fallBack(Taxonomy $taxonomy): void
    {
        if ($taxonomy->imported_at === null) {
            $taxonomy->delete();
        } else {
            $taxonomy->forceFill(['status' => Taxonomy::DONE])->save();
        }

        $english = Taxonomy::query()->firstOrNew(['key' => $taxonomy->key, 'locale' => Taxonomies::FALLBACK]);
        $english->forceFill(['status' => Taxonomy::QUEUED, 'error' => null, 'queued_at' => Carbon::now()])->save();

        $this->run((int) $english->id, null);
    }

    private function stale(Taxonomy $taxonomy): bool
    {
        $minutes = (int) $this->config->get('webx-catalog-feeds.stale_minutes', 30);

        return $taxonomy->queued_at === null || $taxonomy->queued_at->lt(Carbon::now()->subMinutes($minutes));
    }

    /**
     * The rows are updated in place, so what a feed mapped to a category of the list stays mapped.
     * A category the new list dropped leaves its id behind as a value typed by hand: it may be gone
     * from Google's list, but only a person can say what it should be now.
     */
    private function store(Taxonomy $taxonomy, TaxonomyFile $file, string $source): Taxonomy
    {
        return DB::transaction(static function () use ($taxonomy, $file, $source): Taxonomy {
            $taxonomy->forceFill([
                'version' => $file->version,
                'source' => $source,
                'imported_at' => Carbon::now(),
                'status' => Taxonomy::DONE,
                'error' => null,
            ])->save();

            $id = (int) $taxonomy->id;
            $held = DB::table('catalog_feed_taxonomy_items')->where('taxonomy_id', $id)->pluck('id', 'external_id')->all();
            $gone = array_diff_key($held, $file->items);

            foreach (array_chunk($gone, self::BATCH, true) as $batch) {
                foreach (array_flip($batch) as $item => $external) {
                    DB::table('catalog_feed_categories')->where('taxonomy_item_id', $item)->update(['value' => (string) $external]);
                }

                DB::table('catalog_feed_taxonomy_items')->whereIn('id', $batch)->delete();
            }

            $rows = [];

            foreach ($file->items as $external => $path) {
                $rows[] = ['taxonomy_id' => $id, 'external_id' => (string) $external, 'path' => $path];
            }

            foreach (array_chunk($rows, self::BATCH) as $batch) {
                DB::table('catalog_feed_taxonomy_items')->upsert($batch, ['taxonomy_id', 'external_id'], ['path']);
            }

            return $taxonomy;
        });
    }
}
