<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Columns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Exchange\DescribesCell;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\Locales;

/**
 * The main category (`category`) or the additional ones (`categories`, paths through `;`), by
 * path of names or `#id` — {@see CategoryPaths}.
 */
final class CategoryColumn implements DescribesCell, ExchangeColumn
{
    public function __construct(private readonly bool $multiple = false) {}

    public function key(): string
    {
        return $this->multiple ? 'categories' : 'category';
    }

    public function label(): string
    {
        return (string) __('webx-catalog::product.'.$this->key());
    }

    public function field(): string
    {
        return $this->multiple ? 'categories' : 'category_id';
    }

    public function localized(): bool
    {
        return false;
    }

    public function cellFormat(): string
    {
        return $this->multiple
            ? 'Additional categories: several paths through ";", each as in category.'
            : 'The main category: a path of names in the default language from the top through "/" (Electronics/Phones), '
                .'case aside, or #id. Two sisters of one name are refused — use #id. A missing one is created with create_missing.';
    }

    public function export(Collection $products, ?string $locale): array
    {
        $paths = new CategoryPaths(app(Locales::class)->defaultCode());
        $ids = $this->multiple ? $this->extra($products) : [];
        $cells = [];

        foreach ($products as $product) {
            /** @var Product $product */
            $of = $this->multiple ? ($ids[(int) $product->id] ?? []) : array_filter([(int) $product->category_id]);
            $cells[(int) $product->id] = implode(';', array_map($paths->path(...), $of));
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        $paths = CategoryPaths::of($context);

        if (! $this->multiple) {
            return $paths->resolve($cell, $context);
        }

        $ids = [];

        foreach (explode(';', $cell) as $one) {
            if (trim($one) !== '') {
                // Taken again each time: a category created for the last path is a new tree.
                $ids[] = CategoryPaths::of($context)->resolve($one, $context);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The additional categories of a page, one query, in the order the form keeps them.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, list<int>>
     */
    private function extra(Collection $products): array
    {
        $ids = [];

        // The live ones, as the form's own list is: a category in the bin is not somewhere to file.
        $rows = DB::table('catalog_category_product')
            ->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_category_product.category_id')
            ->whereNull('catalog_categories.deleted_at')
            ->whereIn('catalog_category_product.product_id', $products->map(static fn (Product $product): int => (int) $product->id)->all())
            ->orderBy('catalog_category_product.category_id')
            ->get(['catalog_category_product.product_id', 'catalog_category_product.category_id']);

        foreach ($rows as $row) {
            $ids[(int) $row->product_id][] = (int) $row->category_id;
        }

        return $ids;
    }
}
