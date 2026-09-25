<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Localization\Locales;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Services\Rendering\ServiceQuery;

/**
 * Everything the parts of a recipe page print, worked out once (§5.5) — so a part a site
 * publishes and rewrites is markup over plain data, and no part asks the database for itself.
 *
 *     $recipe        the model — for `extra()`, SEO and anything a site's part wants of it
 *     $pictures      the gallery, resolved; the first is the cover
 *     $title, $lead
 *     $minutes, $servings
 *     $categories    [{ id, title, url }] — visible, with an address in this language
 *     $nutrients     [{ id, title }] — visible
 *     $ingredients, $method   HTML as stored (the field type cleaned it on the way in)
 *     $nutrition     [key => text] — only what is written in this language
 *     $services      cards of the visible related services, when `module-services` is here
 *     $similar       cards (§5.6)
 */
final class RecipePage
{
    public function __construct(
        private readonly Cards $cards,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Recipe $recipe): array
    {
        $locale = $this->locales->current();

        $nutrients = [];

        foreach ($recipe->shownNutrients() as $nutrient) {
            if ($nutrient instanceof RecipeNutrient && $nutrient->is_visible) {
                $nutrients[] = ['id' => (int) $nutrient->getKey(), 'title' => $nutrient->displayName($locale)];
            }
        }

        return [
            'recipe' => $recipe,
            'pictures' => $recipe->pictures($locale),
            'title' => (string) $recipe->getTranslation('title', $locale),
            'lead' => (string) $recipe->getTranslation('lead', $locale),
            'minutes' => $recipe->total_minutes,
            'servings' => $recipe->servings,
            'categories' => $this->cards->categoryLinks($recipe, $locale),
            'nutrients' => $nutrients,
            'ingredients' => $recipe->html('ingredients', $locale),
            'method' => $recipe->html('method', $locale),
            'nutrition' => $recipe->nutrition($locale),
            'services' => $this->services($recipe, $locale),
            'similar' => $this->cards->recipes($recipe->similar(locale: $locale)->all(), $locale),
        ];
    }

    /**
     * The services this recipe is related to, as `services()` shows them — visible, in the order
     * chosen. None without `module-services`: the field is not on the form then, either.
     *
     * @return list<array<string, mixed>>
     */
    private function services(Recipe $recipe, string $locale): array
    {
        if (! class_exists(ServiceQuery::class)) {
            return [];
        }

        $ids = $recipe->related('services')->map(static fn (Model $service): int => (int) $service->getKey())->all();

        if ($ids === []) {
            return [];
        }

        return Container::getInstance()->make(ServiceQuery::class)->only($ids)->locale($locale)->get();
    }
}
