<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Localization\Locales;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Panel\Revision;
use WebxUi\Routing\Models\Route;

/**
 * One recipe as the panel knows it (§5.10): a row of the list, and the `recipe` of the form.
 *
 * The title and the categories are the draft's — what the editor is working on — and the address
 * is the registry's, because that is what the site answers at right now.
 *
 * @mixin Recipe
 */
final class RecipeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Recipe $recipe */
        $recipe = $this->resource;

        $locale = app(Locales::class)->current();
        $shown = $recipe->hasDraft() ? $recipe->withDraft() : $recipe;
        $canonical = $this->canonical($recipe, $locale);
        $cover = $shown->cover($locale);

        return [
            'id' => (int) $recipe->getKey(),
            'title' => $this->title($shown, $locale),
            'slug' => (string) $shown->getTranslation('slug', $locale, fallback: false),
            // Null where the recipe names no slug in this language: it has no address there.
            'path' => $canonical?->path,
            'url' => $canonical === null ? null : $recipe->url($locale),
            'cover' => $cover === null ? null : ['thumb' => $cover['thumb'] ?? $cover['url'] ?? null],
            'minutes' => $shown->total_minutes,
            'status' => $recipe->status(),
            'position' => (int) $recipe->position,
            'categories' => $this->categories($recipe, $locale),
            'published_at' => $recipe->published_at?->toAtomString(),
            'updated_at' => $recipe->updated_at?->toAtomString(),
            'deleted_at' => $recipe->deleted_at?->toAtomString(),
            'revision' => Revision::of($recipe),
        ];
    }

    /** The title, the slug where there is none, the number where there is neither. */
    private function title(Recipe $recipe, string $locale): string
    {
        foreach ([$recipe->getTranslation('title', $locale), $recipe->getTranslation('slug', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$recipe->getKey();
    }

    private function canonical(Recipe $recipe, string $locale): ?Route
    {
        if (! $recipe->relationLoaded('routes')) {
            return $recipe->routeCanonical($locale);
        }

        return $recipe->routes
            ->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL);
    }

    /**
     * In the order they were put in: the first is the main one. The draft's when the draft names
     * them — the loaded rows otherwise, so the list is one query for every row that has no draft.
     *
     * @return list<array{id: int, title: string}>
     */
    private function categories(Recipe $recipe, string $locale): array
    {
        $drafted = $recipe->draftValues()[Recipe::DRAFT_CATEGORIES] ?? null;

        if (is_array($drafted)) {
            $ids = array_values(array_map(intval(...), $drafted));
            $found = RecipeCategory::query()->whereKey($ids)->get()->keyBy(static fn (RecipeCategory $category): int => (int) $category->getKey());
            $categories = array_values(array_filter(array_map(static fn (int $id): ?RecipeCategory => $found->get($id), $ids)));
        } else {
            $categories = $recipe->categories->all();
        }

        return array_map(static fn (RecipeCategory $category): array => [
            'id' => (int) $category->getKey(),
            'title' => $category->displayName($locale),
        ], $categories);
    }
}
