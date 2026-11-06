<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\CatalogFeeds\Build\FeedCategories;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Taxonomy\Taxonomies;

/**
 * `catalog/feeds/{feed}/categories` (§6.2, §11 of the feeds spec): the tree with what each category
 * holds and takes after, and how many of them lead nowhere.
 */
final class FeedCategoryController
{
    public function __construct(
        private readonly FeedCategories $categories,
        private readonly Taxonomies $taxonomies,
    ) {}

    public function index(int $feed): JsonResponse
    {
        return $this->answer(Feed::query()->findOrFail($feed));
    }

    public function update(Request $request, int $feed): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);

        $validated = $request->validate(FeedCategories::RULES);

        $this->categories->set($found, $validated['categories']);

        return $this->answer($found);
    }

    private function answer(Feed $feed): JsonResponse
    {
        $tree = $this->categories->tree($feed);

        return new JsonResponse([
            'data' => $tree,
            'unmapped' => count(FeedCategories::unmapped($tree)),
            'taxonomy' => $feed->taxonomy === null ? null : $this->taxonomies->describe($feed->taxonomy, $feed->locale),
        ]);
    }
}
