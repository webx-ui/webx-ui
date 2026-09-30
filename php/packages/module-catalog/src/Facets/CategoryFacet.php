<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;

/**
 * The category as a facet (§8.3 of the architecture): a tree whose values are category ids and
 * whose slugs are the categories' own — flat and unique on the site (decision 23), so
 * `category_gaming-laptops` needs no second slug for the filter.
 *
 * A product counts under its main category, its additional ones, and every ancestor of each:
 * choosing "Laptops" is choosing everything under it. The ancestors are the engine's to count,
 * not a loop's in PHP — {@see sqlValues()} lists them as pairs.
 *
 * Where the filter stands decides what choosing one does, and that is the storefront's rule, not
 * this class's: on a category page one subcategory is a move to its page, on the root or in the
 * search it is a filter (§8.3 of the architecture).
 */
final class CategoryFacet extends AbstractFacet implements TreeFacet
{
    public const KEY = 'category';

    public function key(): string
    {
        return self::KEY;
    }

    public function kind(): FacetKind
    {
        return FacetKind::Tree;
    }

    public function label(): string
    {
        return (string) __('webx-catalog::storefront.facet-category');
    }

    public function field(): IndexField
    {
        return new IndexField('categories', IndexField::INT, multi: true);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        $labels = [];

        foreach ($this->load($values) as $category) {
            $labels[(string) $category->id] = $category->displayName($locale);
        }

        return $labels;
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function slugs(array $values, string $locale): array
    {
        $slugs = [];

        foreach ($this->load($values) as $category) {
            $slug = $category->getTranslation('slug', $locale, false);

            if (is_string($slug) && $slug !== '') {
                $slugs[(string) $category->id] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        if ($slugs === []) {
            return [];
        }

        $found = Category::query()
            ->where(static function (Builder $any) use ($slugs, $locale): void {
                foreach ($slugs as $slug) {
                    $any->orWhere(static fn (Builder $one): Builder => $one->whereTranslation('slug', $slug, $locale));
                }
            })
            ->get();

        $resolved = [];

        foreach ($found as $category) {
            $slug = $category->getTranslation('slug', $locale, false);

            if (is_string($slug) && in_array($slug, $slugs, true)) {
                $resolved[$slug] = (string) $category->id;
            }
        }

        return $resolved;
    }

    /** A chosen ancestor takes its chosen descendants in: "Laptops" already means "Gaming laptops". */
    public function normalise(FacetValue $value): FacetValue
    {
        if (count($value->values) < 2) {
            return $value;
        }

        $chosen = $this->load($value->values);
        $kept = [];

        foreach ($chosen as $category) {
            $covered = $chosen->contains(static fn (Category $other): bool => ! $other->is($category)
                && $other->lft < $category->lft
                && $other->rgt > $category->rgt);

            if (! $covered) {
                $kept[] = (string) $category->id;
            }
        }

        return FacetValue::of($kept);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void
    {
        $query->inCategories($this->subtreeIds($value->values));
    }

    /**
     * Each product with each category it is filed under — main or additional — and every ancestor
     * of those. Built on the connection of the query it counts, so both halves are prefixed alike.
     */
    public function sqlValues(QueryBuilder $products): QueryBuilder
    {
        $filed = $products->newQuery()
            ->from('catalog_products')
            ->select(['id as product_id', 'category_id'])
            ->whereIn('id', clone $products)
            ->whereNotNull('category_id')
            ->unionAll(
                $products->newQuery()
                    ->from('catalog_category_product')
                    ->select(['product_id', 'category_id'])
                    ->whereIn('product_id', clone $products),
            );

        return $products->newQuery()
            ->fromSub($filed, 'filed')
            ->join('catalog_categories as filed_in', 'filed_in.id', '=', 'filed.category_id')
            ->join('catalog_categories as above', static function (JoinClause $join): void {
                $join->on('above.lft', '<=', 'filed_in.lft')->on('above.rgt', '>=', 'filed_in.rgt');
            })
            ->whereNull('filed_in.deleted_at')
            ->whereNull('above.deleted_at')
            ->distinct()
            ->select(['filed.product_id', 'above.id as value']);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string|null>
     */
    public function parents(array $values): array
    {
        $parents = [];

        foreach ($this->load($values) as $category) {
            $parents[(string) $category->id] = $category->parent_id === null ? null : (string) $category->parent_id;
        }

        return $parents;
    }

    /**
     * Every live category at or under the chosen ones.
     *
     * @param  list<string>  $values
     * @return list<int>
     */
    public function subtreeIds(array $values): array
    {
        $chosen = $this->load($values);

        if ($chosen->isEmpty()) {
            return [0];
        }

        return Category::query()
            ->where(static function (Builder $under) use ($chosen): void {
                foreach ($chosen as $category) {
                    $under->orWhere(static fn (Builder $one): Builder => $one
                        ->where('lft', '>=', $category->lft)
                        ->where('rgt', '<=', $category->rgt));
                }
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return Collection<int, Category>
     */
    private function load(array $values): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0));

        return Category::query()->whereKey($ids)->orderBy('lft')->get();
    }
}
