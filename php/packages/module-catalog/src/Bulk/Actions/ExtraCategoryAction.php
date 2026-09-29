<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk\Actions;

use Illuminate\Validation\Rule;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;

/**
 * Add an additional category to every product chosen, or take one away. The main category is left
 * alone either way: adding it as an additional one would file the product twice under the same
 * shelf (decision 2), and removing the main one is `set-category`'s business.
 */
final class ExtraCategoryAction extends CoreAction
{
    public function __construct(private readonly bool $add) {}

    public function key(): string
    {
        return $this->add ? 'add-category' : 'remove-category';
    }

    public function params(): array
    {
        return [new PartField('category_id', 'category', 'webx-catalog::bulk.params.category', ['required', 'integer'], 'catalog_categories_tree')];
    }

    public function rules(): array
    {
        return ['category_id' => ['required', 'integer', Rule::exists('catalog_categories', 'id')->whereNull('deleted_at')]];
    }

    public function apply(Product $product, array $params): array
    {
        $id = (int) $params['category_id'];

        if ($product->category_id === $id) {
            return [];
        }

        $before = $this->extra($product);

        if ($this->add === in_array($id, $before, true)) {
            return [];
        }

        $this->add
            ? $product->categories()->syncWithoutDetaching([$id])
            : $product->categories()->detach($id);

        // The pivot has no model events, so the product is marked for the engine here.
        $product->touch();

        return [['field' => 'categories', 'from' => $this->names($before), 'to' => $this->names($this->extra($product))]];
    }

    /**
     * @return list<int>
     */
    private function extra(Product $product): array
    {
        /** @var list<int> $ids */
        $ids = $product->categories()->orderBy('catalog_categories.id')->pluck('catalog_categories.id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        return $ids;
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function names(array $ids): array
    {
        return Category::withTrashed()->whereKey($ids)->orderBy('id')->get()
            ->map(static fn (Category $category): string => $category->displayName())
            ->values()
            ->all();
    }
}
