<?php

declare(strict_types=1);

namespace WebxUi\Blog\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Admin\Shortcodes\PageText;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;

/**
 * The articles a block may show (§20): the feed's articles in the feed's order — pinned first,
 * then the newest — narrowed by rubric and cut to a number, as cards rather than models.
 *
 * What a reader may see is the feed's rule, not a step: published, no later than now, and with
 * an address in the language being read. A card is plain data for a block that draws its own
 * markup; a block that prints the blog's cards asks {@see block()} for the models, so a theme's
 * `parts/blog/card` draws them the same way on a home page as in the feed.
 *
 * @extends RecordQuery<Article>
 */
final class ArticleQuery extends RecordQuery
{
    /** What a card prints — the feed's list, so a theme's card needs nothing the feed does not load. */
    public const RELATIONS = ['cover', 'rubrics', 'tags', 'author', 'authorProfile'];

    /**
     * Only what is filed under these rubrics: an id, a slug, a rubric, or a list of them. Nothing
     * is no filter at all.
     *
     * @param  int|string|Rubric|iterable<int|string|Rubric>|null  $rubrics
     */
    public function in(int|string|Rubric|iterable|null $rubrics): self
    {
        return $this->withCategories($rubrics);
    }

    /**
     * The articles a block's `wx-collection` value names, as models, in the order it names them —
     * what the blog's own cards partial is handed. The models the value was built from a moment
     * ago, by the same rule ({@see ShownArticles}); asked again only for a value that was not
     * resolved in this request — a stored one, a test's.
     *
     * @return EloquentCollection<int, Article>
     */
    public static function block(mixed $value): EloquentCollection
    {
        $ids = [];

        foreach (is_array($value) && is_array($value['items'] ?? null) ? $value['items'] : [] as $item) {
            if (is_array($item) && is_int($item['id'] ?? null)) {
                $ids[] = $item['id'];
            }
        }

        if ($ids === []) {
            return new EloquentCollection;
        }

        return Container::getInstance()->make(ShownArticles::class)->take($ids) ?? (new self)->only($ids)->models();
    }

    protected function newQuery(string $locale): Builder
    {
        return Article::query()->published()->with(self::RELATIONS);
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        /** @var Article $record */
        return $record->hasUrlIn($locale);
    }

    protected function categoryModel(): string
    {
        return Rubric::class;
    }

    /** The feed's order whatever was asked: a block of "the latest" is the top of the feed. */
    protected function order(Builder $query, ?int $category): void
    {
        $query->scopes(['inFeedOrder']);
    }

    protected function cards(array $records, string $locale): array
    {
        Container::getInstance()->make(ShownArticles::class)->keep($records);

        return array_map(fn (Article $article): array => $this->card($article, $locale), $records);
    }

    /** @return array<string, mixed> */
    private function card(Article $article, string $locale): array
    {
        $rubric = $article->mainRubric();
        $cover = $article->cover;

        return [
            'id' => (int) $article->getKey(),
            'anchor' => (string) $article->getTranslation('slug', $locale),
            'categories' => $article->rubrics->map(static fn (Rubric $rubric): int => (int) $rubric->getKey())->values()->all(),
            'title' => (string) $article->getTranslation('title', $locale),
            'url' => $article->url($locale),
            // Through PageText: the lead takes shortcodes, and a bare `[phone]` is not for a visitor.
            'lead' => Container::getInstance()->make(PageText::class)->text((string) $article->getTranslation('lead', $locale)) ?? '',
            'cover' => $cover instanceof MediaFile
                ? ['url' => Container::getInstance()->make(FileUrls::class)->url($cover), 'width' => $cover->width, 'height' => $cover->height]
                : null,
            'published_at' => $article->published_at?->toIso8601String(),
            'rubric' => $rubric === null ? null : ['id' => (int) $rubric->getKey(), 'title' => (string) $rubric->getTranslation('title', $locale)],
            'tags' => $article->tags->map(static fn (Tag $tag): array => ['title' => (string) $tag->getTranslation('title', $locale), 'url' => $tag->url($locale)])->values()->all(),
            'author' => $article->authorName($locale),
        ];
    }
}
