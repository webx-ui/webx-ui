<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;

/**
 * «Deleted» — the catalogue's bin (§5, §6.3): two lists, products and categories, newest first,
 * with a search and nothing else. What is in here stays in here until somebody restores it;
 * there is no "delete for ever" (decision 4).
 */
final class DeletedController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['products', 'categories'])],
            'q' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        if (($validated['type'] ?? 'products') === 'categories') {
            $query = Category::onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');

            if ($term !== '') {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $query->where(static fn ($nested) => $nested->whereTranslationLikeAny('name', $like)->orWhere(
                    static fn ($slug) => $slug->whereTranslationLikeAny('slug', $like),
                ));
            }

            return new JsonResponse($query->paginate($perPage)->through(static fn (Category $category): array => [
                'id' => (int) $category->getKey(),
                'name' => $category->displayName(),
                'slug' => $category->getTranslation('slug'),
                'parent' => self::parent($category),
                'deleted_at' => $category->deleted_at?->toAtomString(),
            ])->toArray());
        }

        $query = Product::onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');

        if ($term !== '') {
            $query->matching($term);
        }

        return new JsonResponse($query->paginate($perPage)->through(static function (Product $product): array {
            $category = $product->category_id === null ? null : Category::withTrashed()->find($product->category_id);

            return [
                'id' => (int) $product->getKey(),
                'name' => $product->displayName(),
                'sku' => $product->sku,
                // What a restore will do to it: a gone category means back without one, unpublished.
                'category' => $category === null ? null : [
                    'id' => (int) $category->getKey(),
                    'name' => $category->displayName(),
                    'deleted' => $category->trashed(),
                ],
                'deleted_at' => $product->deleted_at?->toAtomString(),
            ];
        })->toArray());
    }

    /**
     * @return array{id: int, name: string, deleted: bool}|null
     */
    private static function parent(Category $category): ?array
    {
        $parent = $category->parent_id === null ? null : Category::withTrashed()->find($category->parent_id);

        return $parent === null ? null : [
            'id' => (int) $parent->getKey(),
            'name' => $parent->displayName(),
            'deleted' => $parent->trashed(),
        ];
    }
}
