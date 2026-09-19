<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use WebxUi\Blog\Models\Article;

/**
 * The query behind the list of articles: what the filters above it mean (§10, §11).
 *
 * Every filter is a subquery rather than a join, and that is not a preference. An article is in
 * several rubrics and carries several tags, so joining the pivot multiplies the rows: a page of
 * twenty becomes seventeen articles and three of them twice, and the paginator's total is a
 * number that belongs to no list anybody is looking at. `whereHas` asks the same question
 * without changing what is being counted.
 *
 * The five states are two columns and one subquery. Four of them read `published_at` and
 * `draft`; the fifth — taken off the site — is the one that cannot, because an article that was
 * never published and one that was pulled this morning both have an empty `published_at`. Only
 * the history tells them apart, and an editor needs it to: "draft" on something that was live
 * at breakfast is a lie (§10).
 */
final class ArticleList
{
    /** A page of the panel's list. Not `webx-blog.per_page` — that one belongs to the site. */
    public const PER_PAGE = 20;

    /** What the list may be ordered by. Anything else falls back to the order below. */
    private const SORTS = ['published_at', 'updated_at'];

    /**
     * @return Builder<Article>
     */
    public function build(Request $request): Builder
    {
        $query = Article::query()->with(['routes', 'rubrics', 'tags', 'author', 'cover']);

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $this->searching($query, trim((string) $request->query('q', '')));
        $this->withStatus($query, (string) $request->query('status', ''));

        $this->byId($query, 'rubrics', $request->query('rubric'));
        $this->byId($query, 'tags', $request->query('tag'));

        $author = $request->query('author');

        if (is_numeric($author)) {
            $query->where('author_id', (int) $author);
        }

        return $this->ordered($query, (string) $request->query('sort', ''), $request->boolean('trashed'));
    }

    /**
     * Articles whose title or address contains the term, in any language the site has.
     *
     * Every language and not the one the panel is open in: the list draws the title an article
     * has, so one written in English alone is on the screen of a Russian panel and has to be
     * findable from it. Through the package's own scope rather than a hand-written `title->en`,
     * because how a translation is stored is `webx-ui/localization`'s to know.
     *
     * @param  Builder<Article>  $query
     */
    private function searching(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(static function (Builder $nested) use ($like): void {
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
        });
    }

    /**
     * The five states of §10.
     *
     * `scheduled` deliberately does not also say `whereNotNull`: a date greater than now is not
     * null by definition, and a second condition would only say so twice.
     *
     * @param  Builder<Article>  $query
     */
    private function withStatus(Builder $query, string $status): void
    {
        $now = Carbon::now();

        match ($status) {
            Article::STATUS_DRAFT => $query
                ->whereNull('published_at')
                ->whereDoesntHave('versions', static fn (Builder $version): Builder => $version->published()),
            Article::STATUS_UNPUBLISHED => $query
                ->whereNull('published_at')
                ->whereHas('versions', static fn (Builder $version): Builder => $version->published()),
            Article::STATUS_SCHEDULED => $query->where('published_at', '>', $now),
            Article::STATUS_PUBLISHED => $query->where('published_at', '<=', $now)->whereNull('draft'),
            Article::STATUS_MODIFIED => $query->where('published_at', '<=', $now)->whereNotNull('draft'),
            default => $query,
        };
    }

    /**
     * @param  Builder<Article>  $query
     */
    private function byId(Builder $query, string $relation, mixed $id): void
    {
        if (! is_numeric($id)) {
            return;
        }

        $query->whereHas($relation, static fn (Builder $related): Builder => $related->whereKey((int) $id));
    }

    /**
     * Pinned first, then by date, newest first (§10).
     *
     * The date is `published_at` where there is one and `updated_at` where there is not. Without
     * the fallback every draft sorts behind every published article — a hundred of them on a
     * real blog — so the article somebody started this morning would be on page six of its own
     * section, which is the one place nobody looks for it.
     *
     * `id` last so that two articles published in the same second do not swap places between
     * page one and page two, which is how a paginator loses a row and shows another twice.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    private function ordered(Builder $query, string $sort, bool $trashed): Builder
    {
        if ($trashed) {
            return $query->orderByDesc('deleted_at')->orderByDesc('id');
        }

        $column = ltrim($sort, '-');

        if (in_array($column, self::SORTS, true)) {
            return $query
                ->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
                ->orderByDesc('id');
        }

        return $query
            ->orderByDesc('pinned')
            ->orderByRaw('coalesce(published_at, updated_at) desc')
            ->orderByDesc('id');
    }
}
