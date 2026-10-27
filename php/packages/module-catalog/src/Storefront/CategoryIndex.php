<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Support\RequestMemo;

/**
 * The shape of the category tree, and the categories one page has loaded (§8.4).
 *
 * The shape is every category's bounds and whether it is visible (§6.3) — numbers, no words — in
 * the storefront's cache under the generation, so a product page answers «who is above the main
 * category», «is it visible», «what is under it» without a query: the trail, the SEO sources, a
 * shelf narrowed by a category and the handler all asked one of those of the database, some of
 * them twice. A big tree is a few thousand pairs of integers; the names stay out.
 *
 * The categories themselves come from one pool a request (`category.pool`, the one the filter
 * reads its options from): the main category of a product and the ancestors of its trail are one
 * query, and a shelf of cards from the same category is none.
 *
 * Every write to the tree moves the generation (the model is watched) and forgets the request's
 * memory (`Category::booted()`), so neither outlives a save, a move or a restore.
 */
final class CategoryIndex
{
    private const POOL = 'category.pool';

    public function __construct(private readonly StorefrontCache $cache) {}

    /** Published, out of the bin, nothing above it hidden — {@see Category::scopeVisible()} without a query. */
    public function isVisible(int $id): bool
    {
        return isset($this->shape()['visible'][$id]);
    }

    /**
     * The live categories above this one, from the root down.
     *
     * @return list<int>
     */
    public function ancestorIds(int $id): array
    {
        $nodes = $this->shape()['nodes'];

        if (! isset($nodes[$id])) {
            return [];
        }

        [$lft, $rgt] = $nodes[$id];
        $above = [];

        foreach ($nodes as $other => [$otherLft, $otherRgt]) {
            if ($otherLft < $lft && $otherRgt > $rgt) {
                $above[$other] = $otherLft;
            }
        }

        asort($above);

        return array_keys($above);
    }

    /**
     * Every live category at or under these, the way {@see Category::subtree()} has it.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function subtreeIds(array $ids): array
    {
        $nodes = $this->shape()['nodes'];
        $under = [];

        foreach ($ids as $id) {
            if (! isset($nodes[$id])) {
                continue;
            }

            [$lft, $rgt] = $nodes[$id];

            foreach ($nodes as $other => [$otherLft, $otherRgt]) {
                if ($otherLft >= $lft && $otherRgt <= $rgt) {
                    $under[$other] = true;
                }
            }
        }

        return array_keys($under);
    }

    /**
     * The live categories behind these ids, from the request's pool; one query for those not in it.
     *
     * @param  list<int>  $ids
     * @return array<int, Category> by id
     */
    public function find(array $ids): array
    {
        $found = RequestMemo::pool(self::POOL, $ids, static fn (array $missing): Collection => Category::query()->whereKey($missing)->get());
        $byId = [];

        foreach ($found as $category) {
            $byId[(int) $category->getKey()] = $category;
        }

        return $byId;
    }

    /**
     * Put categories loaded some other way — a product's additional ones — into the pool, so the
     * next {@see find()} of them is no query.
     *
     * @param  iterable<Category>  $categories
     */
    public function adopt(iterable $categories): void
    {
        /** @var array<int, Category|null> $pool */
        $pool = RequestMemo::remember(self::POOL, static fn (): array => []);

        foreach ($categories as $category) {
            if ($category->exists && ! $category->trashed()) {
                $pool[(int) $category->getKey()] ??= $category;
            }
        }

        RequestMemo::put(self::POOL, $pool);
    }

    /**
     * The main category of every product, and the ancestors of each, from the pool — one query
     * for all of them on a cold page and none for a second shelf of the same category. The
     * product keeps its `category` relation the way `with('category')` would set it: null for one
     * in no category, or in one in the bin.
     *
     * @param  iterable<Product>  $products
     */
    public function attach(iterable $products, bool $withAncestors = false): void
    {
        $wanted = [];

        foreach ($products as $product) {
            if ($product->category_id !== null && ! $product->relationLoaded('category')) {
                $wanted[] = (int) $product->category_id;
            }
        }

        if ($withAncestors) {
            foreach ($wanted as $id) {
                array_push($wanted, ...$this->ancestorIds($id));
            }
        }

        $found = $wanted === [] ? [] : $this->find($wanted);

        foreach ($products as $product) {
            if (! $product->relationLoaded('category')) {
                $product->setRelation('category', $product->category_id === null ? null : ($found[(int) $product->category_id] ?? null));
            }
        }
    }

    /**
     * Whether a product with these categories loaded is visible (§5) — {@see Product::scopeVisible()}
     * from the shape: published, out of the bin, the main or an additional category visible.
     */
    public function productVisible(Product $product): bool
    {
        if (! $product->exists || $product->trashed() || ! $product->is_published) {
            return false;
        }

        if ($product->category_id !== null && $this->isVisible((int) $product->category_id)) {
            return true;
        }

        foreach ($product->categories as $category) {
            if ($this->isVisible((int) $category->getKey())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{nodes: array<int, array{int, int}>, visible: array<int, true>}
     */
    private function shape(): array
    {
        // The request's copy first: the cache's own memory would answer too, but only while the
        // cache is on — and off, every question would be the whole tree again.
        return RequestMemo::remember('category.shape', fn (): array => $this->cache->remember('tree', [], static fn (): array => self::build()));
    }

    /**
     * @return array{nodes: array<int, array{int, int}>, visible: array<int, true>}
     */
    private static function build(): array
    {
        $rows = Category::withTrashed()
            ->orderBy('lft')
            ->toBase()
            ->get(['id', 'lft', 'rgt', 'is_published', 'deleted_at']);

        $nodes = [];
        $visible = [];
        // The hidden ones met so far whose subtree the walk is still inside, as their `rgt`:
        // in `lft` order, a node is under a hidden one exactly while its `rgt` is below that one's.
        $hidden = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $lft = (int) $row->lft;
            $rgt = (int) $row->rgt;

            while ($hidden !== [] && end($hidden) < $lft) {
                array_pop($hidden);
            }

            $trashed = $row->deleted_at !== null;
            $shown = ! $trashed && (bool) $row->is_published && $rgt > 0;

            if (! $trashed) {
                $nodes[$id] = [$lft, $rgt];
            }

            if ($shown && $hidden === []) {
                $visible[$id] = true;
            }

            if ($trashed || ! (bool) $row->is_published) {
                $hidden[] = $rgt;
            }
        }

        return ['nodes' => $nodes, 'visible' => $visible];
    }
}
