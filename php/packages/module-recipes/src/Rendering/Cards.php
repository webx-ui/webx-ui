<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Routing\Models\Route;

/**
 * A recipe as a template reads it — plain data, not the model (§5.8).
 *
 *     id, anchor, categories   what every element of a `wx-collection` carries
 *     title, url, lead         in the language asked for; `lead` is plain text
 *     cover, gallery           what `wx-media` hands over: url, thumb, width, height…; cover is
 *                              the first picture of the gallery, or null
 *     minutes, servings        numbers or null
 *     nutrients                [{ id, title }] — only the ones shown on the site
 *     fields                   the project's own fields (a patch on `recipes.form`), by name
 *
 * Not the model, because a model in a template is the draft one call away and a query per card
 * nobody sees: every card here is built from what was loaded with the list.
 */
final class Cards
{
    /** What a list of recipes is loaded with, so that no card goes back to the database. */
    public const RELATIONS = ['routes', 'categories', 'nutrients'];

    public function __construct(
        private readonly MediaFiles $files,
        private readonly MediaValues $media,
    ) {}

    /**
     * @param  list<Recipe>  $recipes
     * @return list<array<string, mixed>>
     */
    public function recipes(array $recipes, string $locale): array
    {
        $paths = [];

        foreach ($recipes as $recipe) {
            foreach (is_array($recipe->gallery) ? $recipe->gallery : [] as $picture) {
                if (is_array($picture) && is_string($picture['path'] ?? null)) {
                    $paths[] = $picture['path'];
                }
            }
        }

        // Every picture of the list in one query of the library rather than one per card.
        $this->files->load($paths);

        return array_map(fn (Recipe $recipe): array => $this->recipe($recipe, $locale), $recipes);
    }

    /** @return array<string, mixed> */
    private function recipe(Recipe $recipe, string $locale): array
    {
        $fields = [];

        foreach (array_keys((array) ($recipe->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $recipe->extra((string) $name, $locale);
        }

        $gallery = $this->media->resolveList($recipe->gallery ?? [], $locale);

        return [
            'id' => (int) $recipe->getKey(),
            'anchor' => $this->text($recipe, 'slug', $locale),
            'categories' => $recipe->categories->map(static fn (RecipeCategory $category): int => (int) $category->getKey())->values()->all(),
            'title' => $this->text($recipe, 'title', $locale),
            'url' => $this->url($recipe, $locale),
            'lead' => $this->text($recipe, 'lead', $locale),
            'cover' => $gallery[0] ?? null,
            'gallery' => $gallery,
            'minutes' => $recipe->total_minutes,
            'servings' => $recipe->servings,
            'nutrients' => $recipe->nutrients
                ->filter(static fn (RecipeNutrient $nutrient): bool => $nutrient->is_visible)
                ->map(static fn (RecipeNutrient $nutrient): array => [
                    'id' => (int) $nutrient->getKey(),
                    'title' => $nutrient->displayName($locale),
                ])
                ->values()
                ->all(),
            'fields' => $fields,
        ];
    }

    /** From the loaded registry rows: `url()` would ask the registry again for every card. */
    private function url(Recipe $recipe, string $locale): string
    {
        $row = $recipe->routes->first(
            static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL,
        );

        return $row instanceof Route ? $recipe->urlOf($row->path, $locale) : $recipe->url($locale);
    }

    private function text(Recipe $recipe, string $attribute, string $locale): string
    {
        $value = $recipe->getTranslation($attribute, $locale);

        return is_string($value) ? $value : '';
    }
}
