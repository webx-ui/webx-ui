<?php

declare(strict_types=1);

namespace WebxUi\Blog\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Blog\Models\Tag;
use WebxUi\Routing\Models\Route;

/**
 * Tags, as something to link to.
 *
 * A tag is a word: there is nothing to publish and nothing to hide, so `available` is only whether
 * it has an address in this language at all — which it has not until somebody writes its slug in
 * that language.
 *
 * Whether the page is in the index is a different question (§12 of the blog spec) and deliberately
 * not asked here: linking to a `noindex` page from a menu is an ordinary thing to do, and a picker
 * that dimmed every tag would be telling the editor something untrue about all of them.
 */
final class TagLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'tag';
    }

    public function model(): string
    {
        return Tag::class;
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.tags');
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function order(): int
    {
        return 220;
    }

    public function permission(): string
    {
        return 'blog.taxonomy.manage';
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        $tags = $this->query($locale)
            ->when($query !== '', fn (Builder $found): Builder => $this->matching($found, $query))
            ->orderByTranslation('title', locale: $locale)
            ->limit($limit)
            ->get();

        return array_values($this->candidates($tags, $locale));
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
     * @return Builder<Tag>
     */
    private function query(string $locale): Builder
    {
        return Tag::query()->with(['routes' => static fn ($routes) => $routes
            ->where('locale', $locale)
            ->where('kind', Route::CANONICAL)]);
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
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
     * @param  EloquentCollection<int, Tag>  $tags
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $tags, string $locale): array
    {
        $candidates = [];

        foreach ($tags as $tag) {
            $id = (int) $tag->getKey();
            $canonical = $tag->routes->first();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: Titles::of($tag, $locale),
                url: $canonical instanceof Route ? $tag->urlOf($canonical->path, $locale) : null,
                available: $canonical instanceof Route,
            );
        }

        return $candidates;
    }
}
