<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogFeeds\Build\FeedSelection;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * The products a person took out of one feed (§5.2, §11 of the feeds spec). They stay in the others,
 * and the file changes with the next build.
 */
final class FeedExclusionController
{
    private const AT_ONCE = 200;

    public function index(Request $request, int $feed): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Product::query()->whereIn('catalog_products.id', static function (QueryBuilder $rows) use ($found): void {
            $rows->select('product_id')->from(FeedSelection::EXCLUSIONS)->where('feed_id', $found->id);
        });

        $term = trim((string) ($validated['q'] ?? ''));

        if ($term !== '') {
            $query->matching($term);
        }

        $page = $query->orderBy('catalog_products.id')->paginate((int) ($validated['per_page'] ?? 25));

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => $product->displayName($found->locale),
                'sku' => $product->sku,
            ], $page->items()),
        ]);
    }

    public function store(Request $request, int $feed): Response
    {
        $found = Feed::query()->findOrFail($feed);
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:'.self::AT_ONCE],
            'product_ids.*' => ['integer', 'distinct', 'exists:catalog_products,id'],
        ]);

        $now = Carbon::now();

        DB::table(FeedSelection::EXCLUSIONS)->insertOrIgnore(array_map(static fn (mixed $id): array => [
            'feed_id' => $found->id,
            'product_id' => (int) $id,
            'created_at' => $now,
        ], $validated['product_ids']));

        return new Response(status: 204);
    }

    public function destroy(int $feed, int $product): Response
    {
        $found = Feed::query()->findOrFail($feed);

        DB::table(FeedSelection::EXCLUSIONS)->where('feed_id', $found->id)->where('product_id', $product)->delete();

        return new Response(status: 204);
    }
}
