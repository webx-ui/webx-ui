<?php

declare(strict_types=1);

namespace WebxUi\Blog\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Routing\Models\Route;

/**
 * Rubrics, as something to link to.
 *
 * This is the case a menu was wanted for: a header that names three sections of the blog is three
 * links to rubrics, and before this the only way to write them was by hand.
 *
 * A rubric has no draft and no date — what it has instead is `is_visible`, and hidden means it
 * answers 404 (§2.4 of the blog spec). So that flag is the whole of `available` here.
 */
final class RubricLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'rubric';
    }

    public function model(): string
    {
        return Rubric::class;
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.rubrics');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 210;
    }

    /**
     * The taxonomy has one permission and it is `manage`: there is no read-only rubrics screen to
     * open, so anybody allowed to link to one is somebody allowed to edit them.
     */
    public function permission(): string
    {
        return 'blog.taxonomy.manage';
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        $rubrics = $this->query($locale)
            ->when($query !== '', fn (Builder $found): Builder => $this->matching($found, $query))
            // The order an editor put them in, which is the order they stand in on the site.
            ->orderBy('position')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($rubrics, $locale));
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
     * @return Builder<Rubric>
     */
    private function query(string $locale): Builder
    {
        return Rubric::query()->with(['routes' => static fn ($routes) => $routes
            ->where('locale', $locale)
            ->where('kind', Route::CANONICAL)]);
    }

    /**
     * @param  Builder<Rubric>  $query
     * @return Builder<Rubric>
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
     * @param  EloquentCollection<int, Rubric>  $rubrics
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $rubrics, string $locale): array
    {
        $candidates = [];

        foreach ($rubrics as $rubric) {
            $id = (int) $rubric->getKey();
            $canonical = $rubric->routes->first();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: Titles::of($rubric, $locale),
                url: $canonical instanceof Route ? $rubric->urlOf($canonical->path, $locale) : null,
                available: $rubric->is_visible && $canonical instanceof Route,
            );
        }

        return $candidates;
    }
}
