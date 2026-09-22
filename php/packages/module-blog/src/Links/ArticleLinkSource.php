<?php

declare(strict_types=1);

namespace WebxUi\Blog\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Routing\Models\Route;

/**
 * Articles, as something to link to (§3 of the menu spec).
 *
 * The hint is the rubrics the article is filed under, because that is what tells two articles with
 * similar titles apart; an article in none of them falls back to its date, which is the other
 * thing a blog is sorted by.
 *
 * `available` goes through the article's own `isPublished()` and not through the date column: one
 * stamped next Tuesday has a stamp, an address in the registry, and nothing on the site (§7 of the
 * blog spec). A link to it belongs in the picker, marked, and not in a menu.
 */
final class ArticleLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'article';
    }

    public function model(): string
    {
        return Article::class;
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.articles');
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 200;
    }

    public function permission(): string
    {
        return 'blog.articles.view';
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        $articles = $this->query($locale)
            ->when($query !== '', fn (Builder $found): Builder => $this->matching($found, $query))
            ->inFeedOrder()
            ->limit($limit)
            ->get();

        return array_values($this->candidates($articles, $locale));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, LinkCandidate>
     */
    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Article>
     */
    private function query(string $locale): Builder
    {
        return Article::query()->with([
            'rubrics',
            'routes' => static fn ($routes) => $routes
                ->where('locale', $locale)
                ->where('kind', Route::CANONICAL),
        ]);
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    private function matching(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(static function (Builder $nested) use ($like): void {
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
        });
    }

    /**
     * @param  EloquentCollection<int, Article>  $articles
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $articles, string $locale): array
    {
        $candidates = [];

        foreach ($articles as $article) {
            $id = (int) $article->getKey();
            $canonical = $article->routes->first();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: Titles::of($article, $locale),
                url: $canonical instanceof Route ? $article->urlOf($canonical->path, $locale) : null,
                available: $article->isPublished() && $canonical instanceof Route,
                hint: $this->hint($article, $locale),
            );
        }

        return $candidates;
    }

    private function hint(Article $article, string $locale): ?string
    {
        $rubrics = $article->rubrics
            ->map(static fn (Rubric $rubric): string => Titles::of($rubric, $locale))
            ->all();

        if ($rubrics !== []) {
            return implode(', ', $rubrics);
        }

        return $article->published_at?->toDateString();
    }
}
