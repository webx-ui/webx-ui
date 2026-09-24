<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;

/**
 * What an agent reads before it writes a recipe (§5.11): the whole catalogue in one message.
 *
 * Every category, hidden ones too, each with its recipes in the one order recipes have, the
 * recipes filed nowhere at the end, and what recipes can be rich in. Drafts are in it and say so —
 * the point of reading this first is not to write a second "Oatmeal with berries" beside one that
 * is half-written. A recipe in two categories is listed under both: that is where a reader meets
 * it. The categories and the nutrients are the published ones — what a draft is about to change
 * is in `recipes_get`.
 *
 * One title per row, in the language the agent works in; `written_in` says which languages the
 * recipe has a title in at all, and `recipes_get` has every language of the one it picks.
 */
final class RecipesResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'recipes://catalog',
                'Recipes catalog',
                'Every recipe category in its order with its recipes, their addresses, whether they are on the '
                .'site and the languages they are written in; the recipes in no category at the end; and the list '
                .'of what a recipe can be rich in. Read it before creating a recipe, a category or a nutrient, so '
                .'that you reuse rather than duplicate.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);
        $locale = $locales->current();
        $prefix = (string) config('webx-recipes.prefix', 'recipes');

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'prefix' => $prefix,
            // Null when the module's index is switched off: a page may stand at that address.
            'index_url' => (bool) config('webx-recipes.index', true) ? url($prefix) : null,
            'categories' => RecipeCategory::query()->ordered()->get()->map(fn (RecipeCategory $category): array => [
                'id' => (int) $category->getKey(),
                'title' => $category->displayName($locale),
                'slug' => (string) $category->getTranslation('slug', $locale),
                'url' => $category->hasUrlIn($locale) ? $category->url($locale) : null,
                'visible' => (bool) $category->is_visible,
                'recipes' => $this->rows(Recipe::query()->orderedIn((int) $category->getKey())->with('routes')->get()->all(), $locales),
            ])->values()->all(),
            'uncategorised' => $this->rows(
                Recipe::query()->whereDoesntHave('categories')->orderedIn()->with('routes')->get()->all(),
                $locales,
            ),
            'nutrients' => RecipeNutrient::query()->ordered()->get()->map(static fn (RecipeNutrient $nutrient): array => [
                'id' => (int) $nutrient->getKey(),
                'title' => $nutrient->displayName($locale),
                'visible' => (bool) $nutrient->is_visible,
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<Recipe>  $recipes
     * @return list<array<string, mixed>>
     */
    private function rows(array $recipes, Locales $locales): array
    {
        $locale = $locales->current();
        $codes = $locales->codes();

        return array_map(static function (Recipe $recipe) use ($locale, $codes): array {
            $shown = $recipe->hasDraft() ? $recipe->withDraft() : $recipe;

            return [
                'id' => (int) $recipe->getKey(),
                'title' => (string) $shown->getTranslation('title', $locale),
                'url' => $recipe->hasUrlIn($locale) ? $recipe->url($locale) : null,
                'status' => $recipe->status(),
                'written_in' => array_values(array_filter(
                    $codes,
                    static fn (string $code): bool => trim((string) $shown->getTranslation('title', $code, fallback: false)) !== '',
                )),
            ];
        }, $recipes);
    }
}
