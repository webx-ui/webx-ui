<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Admin\Categories\Category;
use WebxUi\Localization\Locales;

/**
 * One category as a row of its list: a name, an address and how many items are in it.
 *
 * The translated fields travel as maps — that is what a localized field edits — and a display
 * name beside them, because a category named in one language and not in another must still be
 * a row somebody can click. The number is why the delete button is greyed and what its
 * explanation says, so it is in the row rather than asked for when the button is pressed; it
 * travels under the module's own word (`articles_count`).
 */
final class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Model&Category $category */
        $category = $this->resource;

        $kind = $category::categoryKind();
        $locale = app(Locales::class)->current();
        $path = $this->path($category, $locale);
        $count = $category->getAttribute($kind->countKey());

        return [
            'id' => (int) $category->getKey(),
            'name' => $category->displayName($locale),
            'title' => $category->categoryValue('title'),
            'slug' => $category->categoryValue('slug'),
            // Null rather than an empty string where the category names no slug in this
            // language: a category translated into one language has no address in the others.
            'path' => $path,
            'url' => $path === null || ! method_exists($category, 'urlOf') ? null : $category->urlOf($path, $locale),
            'is_visible' => (bool) $category->getAttribute('is_visible'),
            'position' => (int) $category->getAttribute('position'),
            $kind->countKey() => $count === null ? $category->itemCount() : (int) $count,
            'deleted_at' => $category->getAttribute('deleted_at')?->toAtomString(),
        ];
    }

    /**
     * The address the registry holds for this language, when the category has addresses at all
     * — out of what was loaded with it, so a list is one query rather than one per row.
     */
    private function path(Model $category, string $locale): ?string
    {
        if (! method_exists($category, 'routeCanonical')) {
            return null;
        }

        if (! $category->relationLoaded('routes')) {
            $route = $category->routeCanonical($locale);

            return $route === null ? null : (string) $route->getAttribute('path');
        }

        // `canonical` is `Route::CANONICAL` of `webx-ui/routing`, which the frame does not require.
        $route = $category->getRelation('routes')->first(
            static fn (Model $route): bool => $route->getAttribute('locale') === $locale && $route->getAttribute('kind') === 'canonical',
        );

        return $route instanceof Model ? (string) $route->getAttribute('path') : null;
    }
}
