<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Category;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;

/**
 * The tree of the panel (§6.1): every live category nested under its parent, with the number of
 * live products in it and everything below it — main and additional alike, each product once.
 *
 * The numbers are one query over the bounds for the whole tree, not a count per node: a node
 * owns every row whose bounds sit inside its own, and a product filed twice in one branch is
 * still one product there. Visibility is worked out on the way down — a node is visible when it
 * is published and its parent is — which is the same rule `Category::scopeVisible()` asks the
 * database, without asking it once per node.
 */
final class CategoryTree
{
    public function __construct(private readonly Locales $locales) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function build(): array
    {
        $categories = Category::query()->with('routes')->placed()->orderBy('lft')->get();
        $counts = $this->counts();

        /** @var array<int, array<string, mixed>> $nodes */
        $nodes = [];
        /** @var list<int> $roots */
        $roots = [];

        foreach ($categories as $category) {
            $id = (int) $category->getKey();
            $parent = $category->parent_id;
            $parentVisible = $parent === null ? true : (bool) ($nodes[$parent]['visible'] ?? false);

            $nodes[$id] = [
                'id' => $id,
                'parent_id' => $parent,
                'name' => $category->displayName(),
                'slug' => $category->getTranslation('slug'),
                'depth' => $category->getDepth(),
                'is_published' => $category->is_published,
                'visible' => $parentVisible && $category->is_published,
                'products_count' => $counts[$id] ?? 0,
                'url' => $this->url($category),
                'children' => [],
            ];

            if ($parent === null || ! isset($nodes[$parent])) {
                $roots[] = $id;
            }
        }

        // Children are attached bottom-up by reference so the tree is built in one pass over a
        // list already in preorder.
        foreach (array_reverse(array_keys($nodes)) as $id) {
            $parent = $nodes[$id]['parent_id'];

            if ($parent !== null && isset($nodes[$parent])) {
                array_unshift($nodes[$parent]['children'], $nodes[$id]);
            }
        }

        return array_map(static fn (int $id): array => $nodes[$id], $roots);
    }

    /**
     * Category id → live products in it and under it.
     *
     * @return array<int, int>
     */
    public function counts(): array
    {
        $filed = DB::table('catalog_products')
            ->select(['id as product_id', 'category_id'])
            ->whereNull('deleted_at')
            ->whereNotNull('category_id')
            ->unionAll(
                DB::table('catalog_category_product')
                    ->join('catalog_products', 'catalog_products.id', '=', 'catalog_category_product.product_id')
                    ->whereNull('catalog_products.deleted_at')
                    ->select(['catalog_category_product.product_id', 'catalog_category_product.category_id'])
            );

        $query = DB::table('catalog_categories as node');
        // Wrapped by the grammar rather than written raw: an alias is prefixed like a table, and a
        // raw `node.id` would miss it on a site with a table prefix.
        $grammar = $query->getGrammar();

        return $query
            ->join('catalog_categories as inner_node', static function ($join): void {
                $join->on('inner_node.lft', '>=', 'node.lft')
                    ->on('inner_node.rgt', '<=', 'node.rgt');
            })
            ->joinSub($filed, 'filed', 'filed.category_id', '=', 'inner_node.id')
            ->whereNull('node.deleted_at')
            ->whereNull('inner_node.deleted_at')
            ->groupBy('node.id')
            ->select('node.id')
            ->selectRaw('count(distinct '.$grammar->wrap('filed.product_id').') as products')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(int) $row->id => (int) $row->products])
            ->all();
    }

    private function url(Category $category): ?string
    {
        $locale = $this->locales->current();
        $row = $category->routes->first(
            static fn (Route $route): bool => $route->kind === Route::CANONICAL && $route->locale === $locale,
        );

        return $row === null ? null : $category->urlOf($row->path);
    }
}
