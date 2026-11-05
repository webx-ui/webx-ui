<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * `/api/cms/catalog/feeds` (§11 of the feeds spec): the list, a feed and its writes.
 */
final class FeedController
{
    public function __construct(
        private readonly FeedForm $form,
        private readonly FeedResource $resource,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = Feed::query()->with('lastBuild')->orderBy('id')->paginate((int) ($validated['per_page'] ?? 50));
        $user = $request->user();

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(fn (Feed $feed): array => $this->resource->list($feed, $user), $page->items()),
        ]);
    }

    public function show(Request $request, int $feed): JsonResponse
    {
        return $this->answer($request, Feed::query()->findOrFail($feed));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->answer($request, $this->form->save(new Feed, $request->all()), 201);
    }

    public function update(Request $request, int $feed): JsonResponse
    {
        return $this->answer($request, $this->form->save(Feed::query()->findOrFail($feed), $request->all()));
    }

    public function destroy(int $feed): Response
    {
        Feed::query()->findOrFail($feed)->delete();

        return new Response(status: 204);
    }

    private function answer(Request $request, Feed $feed, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $this->resource->full($feed, $request->user())], $status);
    }
}
