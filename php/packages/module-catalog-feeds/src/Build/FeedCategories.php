<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * Our categories against a marketplace's, per feed (§6.2 of the feeds spec). A row holds a category
 * of the list or a value typed by hand; the file gets the list's id, which is what Google asks for.
 * No row means the category takes after its parent.
 *
 * @phpstan-import-type Mapped from CategoryMap
 */
final class FeedCategories
{
    private const TABLE = 'catalog_feed_categories';

    /** Two queries, and one when nothing is mapped: asked once a page. */
    public function map(Feed $feed): CategoryMap
    {
        $own = $this->own($feed);

        if ($own === []) {
            return new CategoryMap([], []);
        }

        $parents = [];

        foreach (Category::query()->toBase()->get(['id', 'parent_id']) as $category) {
            $parents[(int) $category->id] = $category->parent_id === null ? null : (int) $category->parent_id;
        }

        return new CategoryMap($own, $parents);
    }

    /** What a write of mappings has to pass, from the panel and from an agent. */
    public const RULES = [
        'categories' => ['present', 'array', 'max:5000'],
        'categories.*.category_id' => ['required', 'integer', 'distinct', 'exists:catalog_categories,id'],
        'categories.*.taxonomy_item_id' => ['nullable', 'integer', 'exists:catalog_feed_taxonomy_items,id', 'prohibits:categories.*.value'],
        'categories.*.value' => ['nullable', 'string', 'max:255'],
    ];

    /**
     * The rows of {@see tree()} that lead nowhere: nothing of their own, no parent to take after.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    public static function unmapped(array $tree): array
    {
        return array_values(array_filter($tree, static fn (array $row): bool => $row['value'] === null && $row['inherited'] === null));
    }

    /**
     * Every category with what it holds and what it takes after: what the form draws a tree from.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(Feed $feed): array
    {
        $map = $this->map($feed);
        $rows = [];

        foreach (Category::query()->orderBy('lft')->get() as $category) {
            /** @var Category $category */
            $id = (int) $category->id;
            $found = $map->find($id);
            $own = $found !== null && $found['category'] === $id;

            $rows[] = [
                'id' => $id,
                'parent_id' => $category->parent_id === null ? null : (int) $category->parent_id,
                'name' => $category->displayName($feed->locale),
                'taxonomy_item_id' => $own ? $found['item'] : null,
                'value' => $own ? $found['value'] : null,
                'label' => $own ? $found['label'] : null,
                'inherited' => $found !== null && ! $own ? [
                    'category_id' => $found['category'],
                    'value' => $found['value'],
                    'label' => $found['label'],
                ] : null,
            ];
        }

        return $rows;
    }

    /**
     * Only the categories named change. One named with nothing goes back to taking after its parent.
     *
     * @param  list<array{category_id: int, taxonomy_item_id?: int|null, value?: string|null}>  $rows
     */
    public function set(Feed $feed, array $rows): void
    {
        DB::transaction(function () use ($feed, $rows): void {
            foreach ($rows as $row) {
                $key = ['feed_id' => (int) $feed->id, 'category_id' => (int) $row['category_id']];
                $item = $row['taxonomy_item_id'] ?? null;
                $value = $row['value'] ?? null;

                if ($item === null && $value === null) {
                    DB::table(self::TABLE)->where($key)->delete();
                } else {
                    DB::table(self::TABLE)->updateOrInsert($key, ['taxonomy_item_id' => $item, 'value' => $item === null ? $value : null]);
                }
            }
        });
    }

    /**
     * @return array<int, Mapped>
     */
    private function own(Feed $feed): array
    {
        if (! $feed->exists) {
            return [];
        }

        $rows = DB::table(self::TABLE.' as mapped')
            ->leftJoin('catalog_feed_taxonomy_items as item', 'item.id', '=', 'mapped.taxonomy_item_id')
            ->where('mapped.feed_id', $feed->id)
            ->get(['mapped.category_id', 'mapped.taxonomy_item_id', 'mapped.value', 'item.external_id', 'item.path']);

        $own = [];

        foreach ($rows as $row) {
            $value = (string) ($row->external_id ?? $row->value);

            if ($value !== '') {
                $own[(int) $row->category_id] = [
                    'category' => (int) $row->category_id,
                    'value' => $value,
                    'item' => $row->taxonomy_item_id === null ? null : (int) $row->taxonomy_item_id,
                    'label' => $row->path === null ? null : (string) $row->path,
                ];
            }
        }

        return $own;
    }
}
