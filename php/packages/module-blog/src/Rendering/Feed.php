<?php

declare(strict_types=1);

namespace WebxUi\Blog\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Seo\Rendering\Seo;

/**
 * The three listings, which are one listing asked three questions (§5).
 *
 * The feed, a rubric page and a tag page differ in their heading and in one `whereHas`; they
 * agree about everything that can go wrong — what counts as published, what order things come
 * in, how many fit on a page, and which relations to load before the view asks for them in a
 * loop. Writing that three times is writing three different answers to "is a scheduled article
 * in the feed".
 */
final class Feed
{
    public function __construct(
        private readonly Config $config,
        private readonly Seo $seo,
    ) {}

    /**
     * Everything on the site, newest first, pinned above it (§5).
     *
     * @return LengthAwarePaginator<int, Article>
     */
    public function all(): LengthAwarePaginator
    {
        return $this->paginate(Article::query());
    }

    /**
     * @return LengthAwarePaginator<int, Article>
     */
    public function inRubric(Rubric $rubric): LengthAwarePaginator
    {
        return $this->paginate(
            Article::query()->whereHas('rubrics', static fn ($query) => $query->whereKey($rubric->getKey())),
        );
    }

    /**
     * @return LengthAwarePaginator<int, Article>
     */
    public function withTag(Tag $tag): LengthAwarePaginator
    {
        return $this->paginate(
            Article::query()->whereHas('tags', static fn ($query) => $query->whereKey($tag->getKey())),
        );
    }

    /**
     * What one account wrote, on the page of its author (§19.3) — by `author_id`, the same
     * column the articles have always had.
     *
     * @return LengthAwarePaginator<int, Article>
     */
    public function byAuthor(Author $author): LengthAwarePaginator
    {
        return $this->paginate(Article::query()->where('author_id', $author->user_id));
    }

    /**
     * The last few, for the RSS. Not paginated and not pinned-first: a reader's feed reader
     * orders by date and a pinned article at the top of every fetch would be read as new.
     *
     * @return Collection<int, Article>
     */
    public function latest(int $limit): Collection
    {
        /** @var Collection<int, Article> $articles */
        $articles = Article::query()
            ->published()
            ->with($this->eagerLoad())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $articles;
    }

    public function perPage(): int
    {
        $configured = (int) $this->config->get('webx-blog.per_page', 12);

        return $configured > 0 ? $configured : 12;
    }

    /**
     * @param  Builder<Article>  $query
     * @return LengthAwarePaginator<int, Article>
     */
    private function paginate(Builder $query): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Article> $page */
        $page = $query
            ->published()
            ->inFeedOrder()
            ->with($this->eagerLoad())
            ->paginate($this->perPage());

        // A listing is a page of a list wherever it is printed: its neighbours go into the head.
        $this->seo->paginate($page);

        return $page;
    }

    /**
     * What a card in a listing prints: a cover, the rubric it names, its tags and a byline. Loaded
     * here rather than in the view, because a view that loads them loads them once per row.
     *
     * @return list<string>
     */
    private function eagerLoad(): array
    {
        // `routes` too: `url()` answers from the loaded rows, and without them a card is a query.
        return ['cover', 'rubrics', 'tags', 'author', 'authorProfile', 'routes'];
    }
}
