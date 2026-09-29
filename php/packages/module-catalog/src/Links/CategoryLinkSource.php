<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Catalog\Models\Category;
use WebxUi\Routing\Models\Route;

/**
 * The catalogue's categories, for a menu — the header of a shop is mostly these (§14).
 *
 * Not the frame's `CategoryLinkSource`: that one is for the flat, ordered categories of
 * `module-admin`, and these are a tree whose visibility is inherited — a published category under
 * an unpublished one answers 404, so available is visible, not the category's own flag. Listed in
 * the order of the tree, with the parent as the hint.
 */
final class CategoryLinkSource implements LinkSource
{
    public function type(): string
    {
        return Category::TYPE;
    }

    public function model(): string
    {
        return Category::class;
    }

    public function title(): string
    {
        return (string) __('webx-catalog::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 300;
    }

    public function permission(): string
    {
        return 'catalog.view';
    }

    public function search(string $query, string $locale, int $limit): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

        $categories = $this->query($locale)
            ->when($query !== '', static fn (Builder $found): Builder => $found->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('name', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
            }))
            ->orderBy('lft')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($categories, $locale));
    }

    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Category>
     */
    private function query(string $locale): Builder
    {
        return Category::query()->with([
            'routes' => static fn ($routes) => $routes->where('locale', $locale)->where('kind', Route::CANONICAL),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Category>  $categories
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $categories, string $locale): array
    {
        $visible = $categories->isEmpty() ? [] : Category::query()->whereKey($categories->modelKeys())->visible()
            ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $parents = Category::query()->whereKey($categories->pluck('parent_id')->filter()->unique()->all())->get()->keyBy('id');
        $candidates = [];

        foreach ($categories as $category) {
            $id = (int) $category->getKey();
            $canonical = $category->routes->first();
            $parent = $category->parent_id === null ? null : $parents->get($category->parent_id);

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: $category->displayName($locale),
                url: $canonical instanceof Route ? $category->urlOf($canonical->path, $locale) : null,
                available: $canonical instanceof Route && in_array($id, $visible, true),
                hint: $parent instanceof Category ? $parent->displayName($locale) : null,
            );
        }

        return $candidates;
    }
}
