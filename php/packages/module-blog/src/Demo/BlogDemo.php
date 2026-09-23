<?php

declare(strict_types=1);

namespace WebxUi\Blog\Demo;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;

/**
 * A rubric, two tags and two articles — one of them dated a week ahead, so that «Scheduled» is
 * something a new site can see rather than read about (§9 of the new-site spec).
 *
 * The dates are relative to the day the demo is run: an article stamped with a date written
 * into a fixture is one that reads as ancient on every site installed after it.
 *
 * The cover comes out of the journal rather than out of a query, which is what `requires()`
 * buys: the library seeded its two pictures a moment ago, and the first of them is the wide
 * one. Nothing here guesses at a file name, and a site whose library already held those bytes
 * gets an article without a cover instead of somebody else's picture on it.
 */
final class BlogDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $document = $this->read();

        // A blog with anything in it is somebody's blog.
        if (Article::withTrashed()->exists() || Rubric::withTrashed()->exists()) {
            return;
        }

        $rubric = $this->rubric($document['rubric'] ?? [], $ledger);
        $tags = $this->tags($document['tags'] ?? [], $ledger);
        $cover = $this->cover($ledger);

        foreach (is_array($document['articles'] ?? null) ? $document['articles'] : [] as $article) {
            if (is_array($article)) {
                $this->article($article, $rubric, $tags, $cover, $ledger);
            }
        }
    }

    /**
     * @param  mixed  $input
     */
    private function rubric($input, DemoLedger $ledger): Rubric
    {
        $input = is_array($input) ? $input : [];

        $rubric = Rubric::query()->create([
            'title' => $this->words($input['title'] ?? 'Notes'),
            'slug' => $this->words($input['slug'] ?? 'notes'),
            'lead' => $this->words($input['lead'] ?? null),
            'is_visible' => true,
            'position' => 0,
        ]);

        $ledger->created($rubric, (string) ($input['slug'] ?? 'notes'));

        return $rubric;
    }

    /**
     * @param  mixed  $input
     * @return array<string, Tag>
     */
    private function tags($input, DemoLedger $ledger): array
    {
        $tags = [];

        foreach (is_array($input) ? $input : [] as $one) {
            if (! is_array($one) || ! is_string($one['slug'] ?? null)) {
                continue;
            }

            $tag = Tag::query()->create([
                'title' => $this->words($one['title'] ?? $one['slug']),
                'slug' => $this->words($one['slug']),
                // Out of the index until somebody writes a rule for its address (§9 of the
                // blog spec), which is the module's own default and worth showing as such.
                'noindex' => true,
            ]);

            $ledger->created($tag, $one['slug']);
            $tags[$one['slug']] = $tag;
        }

        return $tags;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, Tag>  $tags
     */
    private function article(array $input, Rubric $rubric, array $tags, ?MediaFile $cover, DemoLedger $ledger): void
    {
        $slug = (string) ($input['slug'] ?? '');

        if ($slug === '') {
            return;
        }

        $article = Article::query()->create([
            'title' => $this->words($input['title'] ?? $slug),
            'slug' => $this->words($slug),
            'lead' => $this->words($input['lead'] ?? null),
            'cover_id' => ($input['cover'] ?? false) === true ? $cover?->getKey() : null,
            'pinned' => (bool) ($input['pinned'] ?? false),
            'blocks' => is_array($input['blocks'] ?? null) ? $input['blocks'] : [],
        ]);

        $ledger->created($article, $slug);

        $article->rubrics()->sync([$rubric->getKey()]);

        $wanted = array_values(array_filter(
            array_map(
                static fn (mixed $name): ?Tag => is_string($name) ? ($tags[$name] ?? null) : null,
                is_array($input['tags'] ?? null) ? $input['tags'] : [],
            ),
        ));

        $article->tags()->sync(array_map(static fn (Tag $tag): int => (int) $tag->getKey(), $wanted));

        // The date is what publishes an article, and a date ahead of today is what «Scheduled»
        // means — there is no job behind it (§7 of the blog spec).
        $article->publish(
            null,
            EntityVersion::SOURCE_IMPORT,
            'Demo content',
            Carbon::now()->addDays((int) ($input['published_in_days'] ?? 0)),
        );

        $ledger->createdVersionsOf($article);
    }

    /** The wide picture the library seeded a moment ago, if it did. */
    private function cover(DemoLedger $ledger): ?MediaFile
    {
        $ids = $ledger->idsOf('media', MediaFile::class);

        return $ids === [] ? null : MediaFile::query()->find($ids[0]);
    }

    /**
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        $text = is_string($text) ? trim($text) : '';

        return $text === '' ? null : [$this->locales->defaultCode() => $text];
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/blog.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('blog.json is not a blog document.');
        }

        return $document;
    }
}
