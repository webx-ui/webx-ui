<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\CatalogFeeds\Build\FeedExclusions;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * The products a person took out of one feed (§5.2, §11 of the feeds spec). They stay in the others,
 * and the file changes with the next build.
 */
final class FeedExclusionController
{
    public function __construct(private readonly FeedExclusions $exclusions) {}

    public function index(Request $request, int $feed): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $this->exclusions->page($found, trim((string) ($validated['q'] ?? '')), (int) ($validated['per_page'] ?? 25));

        return new JsonResponse([
            ...$page->toArray(),
            'data' => $this->exclusions->rows($found, $page->items()),
        ]);
    }

    public function store(Request $request, int $feed): Response
    {
        $found = Feed::query()->findOrFail($feed);
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:'.FeedExclusions::AT_ONCE],
            'product_ids.*' => ['integer', 'distinct', 'exists:catalog_products,id'],
        ]);

        $this->exclusions->add($found, array_map(intval(...), $validated['product_ids']));

        return new Response(status: 204);
    }

    public function destroy(int $feed, int $product): Response
    {
        $this->exclusions->remove(Feed::query()->findOrFail($feed), [$product]);

        return new Response(status: 204);
    }
}
