<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Routing\Models\Route;

/**
 * Recipes, for whatever points at an entity: a menu entry, a link field, a block (§3 of the menu
 * spec). The hint is the categories, which is what tells two recipes called "Porridge" apart.
 */
final class RecipeLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'recipe';
    }

    public function model(): string
    {
        return Recipe::class;
    }

    public function title(): string
    {
        return (string) __('webx-recipes::module.recipes');
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 230;
    }

    public function permission(): string
    {
        return 'recipes.view';
    }

    public function search(string $query, string $locale, int $limit): array
    {
        $recipes = $this->query($locale)
            ->when($query !== '', static fn (Builder $found): Builder => $found->where(static function (Builder $nested) use ($query): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
            }))
            ->orderBy('position')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($recipes, $locale));
    }

    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Recipe>
     */
    private function query(string $locale): Builder
    {
        return Recipe::query()->with([
            'categories',
            'routes' => static fn ($routes) => $routes
                ->where('locale', $locale)
                ->where('kind', Route::CANONICAL),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Recipe>  $recipes
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $recipes, string $locale): array
    {
        $candidates = [];

        foreach ($recipes as $recipe) {
            $id = (int) $recipe->getKey();
            $canonical = $recipe->routes->first();
            $categories = $recipe->categories
                ->map(static fn (RecipeCategory $category): string => $category->displayName($locale))
                ->all();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: self::name($recipe, $locale),
                url: $canonical instanceof Route ? $recipe->urlOf($canonical->path, $locale) : null,
                available: $recipe->isPublished() && $canonical instanceof Route,
                hint: $categories === [] ? null : implode(', ', $categories),
            );
        }

        return $candidates;
    }

    private static function name(Recipe $recipe, string $locale): string
    {
        foreach (['title', 'slug'] as $attribute) {
            $candidate = $recipe->getTranslation($attribute, $locale);

            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$recipe->getKey();
    }
}
