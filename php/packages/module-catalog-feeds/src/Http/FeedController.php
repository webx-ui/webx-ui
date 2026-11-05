<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\CatalogFeeds\Build\FeedTrial;
use WebxUi\CatalogFeeds\Delivery\FeedKey;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * `/api/cms/catalog/feeds` (§11 of the feeds spec): the list, a feed and its writes.
 */
final class FeedController
{
    /** How many products a preview may be asked about (§13). */
    private const NAMED = 20;

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
        return $this->saved($request, $this->form->save(new Feed, $request->all()), 201);
    }

    public function update(Request $request, int $feed): JsonResponse
    {
        return $this->saved($request, $this->form->save(Feed::query()->findOrFail($feed), $request->all()));
    }

    /**
     * The values of the form tried like a save, with nothing kept: a mistake is the answer here, not
     * a refusal. `feed` is the one they belong to, whose exclusions count.
     */
    public function preview(Request $request, FeedTrial $trial): JsonResponse
    {
        $validated = $request->validate([
            'feed' => ['sometimes', 'nullable', 'integer'],
            'product_ids' => ['sometimes', 'array', 'max:'.self::NAMED],
            'product_ids.*' => ['integer'],
        ]);

        $feed = isset($validated['feed']) ? Feed::query()->findOrFail((int) $validated['feed']) : new Feed;
        $this->form->fill($feed, $request->all());

        return new JsonResponse(['data' => $trial->preview($feed, array_map(intval(...), $validated['product_ids'] ?? []))->toArray()]);
    }

    public function destroy(int $feed): Response
    {
        Feed::query()->findOrFail($feed)->delete();

        return new Response(status: 204);
    }

    /** The answer is the only place the new address is shown: it goes into the marketplace by hand. */
    public function reissueKey(Request $request, FeedKey $key, int $feed): JsonResponse
    {
        return $this->answer($request, $key->reissue(Feed::query()->findOrFail($feed)));
    }

    private function saved(Request $request, FeedSaved $saved, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->resource->full($saved->feed, $request->user()),
            'warnings' => $saved->warnings,
        ], $status);
    }

    private function answer(Request $request, Feed $feed, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $this->resource->full($feed, $request->user())], $status);
    }
}
