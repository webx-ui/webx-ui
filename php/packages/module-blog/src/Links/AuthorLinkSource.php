<?php

declare(strict_types=1);

namespace WebxUi\Blog\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Blog\Models\Author;
use WebxUi\Routing\Models\Route;

/**
 * Authors with a page, as something to link to (§19) — a header naming the people who write
 * the blog is a row of links to their pages.
 *
 * Every profile is a candidate, but only an open one is `available`: a link to the page of an
 * author who has not asked for one, or on a site that does not allow them, is a link to a 404
 * (§19.1, decision 2).
 */
final class AuthorLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'author';
    }

    public function model(): string
    {
        return Author::class;
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.authors');
    }

    public function icon(): string
    {
        return 'user';
    }

    public function order(): int
    {
        return 230;
    }

    public function permission(): string
    {
        return 'blog.authors.manage';
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        $authors = $this->query($locale)
            ->when($query !== '', fn (Builder $found): Builder => $this->matching($found, $query))
            ->orderByTranslation('title', locale: $locale)
            ->limit($limit)
            ->get();

        return array_values($this->candidates($authors, $locale));
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
     * @return Builder<Author>
     */
    private function query(string $locale): Builder
    {
        return Author::query()->with(['routes' => static fn ($routes) => $routes
            ->where('locale', $locale)
            ->where('kind', Route::CANONICAL)]);
    }

    /**
     * @param  Builder<Author>  $query
     * @return Builder<Author>
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
     * @param  EloquentCollection<int, Author>  $authors
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $authors, string $locale): array
    {
        $candidates = [];

        foreach ($authors as $author) {
            $id = (int) $author->getKey();
            $canonical = $author->routes->first();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: Titles::of($author, $locale),
                url: $canonical instanceof Route ? $author->urlOf($canonical->path, $locale) : null,
                available: $canonical instanceof Route && $author->isOpen(),
            );
        }

        return $candidates;
    }
}
