<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk\Actions;

use Illuminate\Validation\Rule;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;

/**
 * Make one category the main one of every product chosen. A product that had it as an additional
 * one stops having it there: the main category is never among the additional (decision 2).
 */
final class SetCategoryAction extends CoreAction
{
    public function key(): string
    {
        return 'set-category';
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

        $product->category_id = $id;
        $product->save();
        $product->categories()->detach($id);

        return $product->takeHistoryChanges();
    }
}
