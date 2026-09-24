<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use WebxUi\Localization\Locales;
use WebxUi\Recipes\Models\Recipe;

/**
 * Where a saved recipe goes: all of it into the draft (§5.14).
 *
 * Unlike a service, whose categories take effect when they are saved, a recipe's categories and
 * nutrients wait in the draft with the text — `category_ids` and `nutrient_ids` — and publishing
 * writes them into the link tables. A kind of category put back the way the site has it leaves
 * the draft, so a save that changes nothing does not mark the recipe "changed".
 *
 * The draft is always built from the one there is ({@see Recipe::draftValues()}): the relations
 * wait in it under their own key, and a draft built from scratch would drop them.
 */
final class RecipeWriter
{
    /** The fields that are a map of languages rather than a value. */
    private const TRANSLATED = ['title', 'slug', 'lead', 'ingredients', 'method'];

    public function __construct(private readonly Locales $locales) {}

    /**
     * @param  array<string, mixed>  $columns  The recipe's own fields, only the ones that were sent.
     * @param  list<int>|null  $categories  Null leaves them alone; an empty list clears them.
     * @param  list<int>|null  $nutrients  The same.
     */
    public function save(Recipe $recipe, array $columns, ?array $categories = null, ?array $nutrients = null, ?int $authorId = null): Recipe
    {
        if ($columns === [] && $categories === null && $nutrients === null) {
            return $recipe;
        }

        $values = $this->draft($recipe, $columns);

        foreach (['categories' => $categories, 'nutrients' => $nutrients] as $relation => $ids) {
            if ($ids === null) {
                continue;
            }

            $key = Recipe::draftKey($relation);

            if ($ids === $recipe->categoryIds($relation)) {
                unset($values[$key]);
            } else {
                $values[$key] = $ids;
            }
        }

        $recipe->saveDraft($values, $authorId);

        return $recipe;
    }

    /**
     * The whole draft, with what was sent laid over what the editor is looking at.
     *
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. So does the nutrition,
     * one key at a time: a language nobody mentioned is a language nobody meant to delete.
     *
     * @param  array<string, mixed>  $columns
     * @return array<string, mixed>
     */
    private function draft(Recipe $recipe, array $columns): array
    {
        $locale = $this->locales->current();
        $values = $recipe->hasDraft() ? $recipe->draftValues() : $this->published($recipe);

        foreach ($columns as $field => $value) {
            if ($field === 'nutrition') {
                $values['nutrition'] = $this->nutrition($values['nutrition'] ?? null, $value, $locale);

                continue;
            }

            if (! in_array($field, self::TRANSLATED, true)) {
                $values[$field] = $value;

                continue;
            }

            $values[$field] = $this->translated($values[$field] ?? null, $value, $locale);
        }

        return $values;
    }

    private function translated(mixed $current, mixed $value, string $locale): mixed
    {
        if (is_array($value)) {
            return is_array($current) ? [...$current, ...$value] : $value;
        }

        $map = is_array($current) ? $current : ($current === null ? [] : [$locale => $current]);
        $map[$locale] = $value;

        return $map;
    }

    /**
     * @param  array<string, mixed>  $sent  Key → a map of languages, or one string in the panel's.
     * @return array<string, array<string, string>>
     */
    private function nutrition(mixed $current, mixed $sent, string $locale): array
    {
        $values = is_array($current) ? $current : [];

        foreach (is_array($sent) ? $sent : [] as $key => $words) {
            $values[$key] = $this->translated($values[$key] ?? null, $words, $locale);
        }

        return Recipe::cleanNutrition($values);
    }

    /**
     * The recipe as the site has it — the starting point for a draft that does not exist yet.
     *
     * @return array<string, mixed>
     */
    private function published(Recipe $recipe): array
    {
        $values = [
            'gallery' => $recipe->gallery,
            'nutrition' => $recipe->nutrition,
            'total_minutes' => $recipe->total_minutes,
            'servings' => $recipe->servings,
            'extra' => $recipe->extraRaw(),
        ];

        foreach (self::TRANSLATED as $field) {
            $values[$field] = $recipe->getTranslations($field);
        }

        return $values;
    }
}
