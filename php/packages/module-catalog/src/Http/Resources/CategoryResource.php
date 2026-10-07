<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Catalog\Models\Category;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;

/**
 * A category as the head of its editor and as the answer to a restore.
 *
 * @mixin Category
 */
final class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;
        $locale = app(Locales::class)->content();
        $routes = $category->relationLoaded('routes') ? $category->routes : $category->routes()->get();
        $row = $routes->first(static fn (Route $route): bool => $route->kind === Route::CANONICAL && $route->locale === $locale);

        return [
            'id' => (int) $category->getKey(),
            'parent_id' => $category->parent_id,
            'name' => $category->displayName(),
            'slug' => $category->getTranslation('slug'),
            'depth' => $category->getDepth(),
            'is_published' => $category->is_published,
            'visible' => $category->isVisible(),
            'products_count' => $category->trashed() ? 0 : $category->productCount(),
            'url' => $row === null ? null : $category->urlOf($row->path, $locale),
            'created_at' => $category->created_at?->toAtomString(),
            'updated_at' => $category->updated_at?->toAtomString(),
            'deleted_at' => $category->deleted_at?->toAtomString(),
        ];
    }
}
