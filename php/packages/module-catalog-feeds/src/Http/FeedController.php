<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\CatalogFeeds\Build\FeedFacets;
use WebxUi\CatalogFeeds\Build\FeedFailure;
use WebxUi\CatalogFeeds\Build\FeedSelection;
use WebxUi\CatalogFeeds\Build\FeedTrial;
use WebxUi\CatalogFeeds\Delivery\FeedKey;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\Localization\Locales;

/**
 * `/api/cms/catalog/feeds` (§11 of the feeds spec): the list, a feed and its writes.
 */
final class FeedController
{
    /** How many products a preview may be asked about (§13). */
    public const NAMED = 20;

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

        $page = Feed::query()->with(['lastBuild', 'currentBuild:id,path'])->orderBy('id')->paginate((int) ($validated['per_page'] ?? 50));
        $user = $request->user();

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(fn (Feed $feed): array => $this->resource->list($feed, $user), $page->items()),
        ]);
    }

    public function meta(FeedMeta $meta): JsonResponse
    {
        return new JsonResponse(['data' => $meta->all()]);
    }

    public function variables(FeedMeta $meta): JsonResponse
    {
        return new JsonResponse(['data' => $meta->variables()]);
    }

    /** What the selection of a feed in this language may name (§5.1). */
    public function facets(Request $request, FeedFacets $facets, Locales $locales): JsonResponse
    {
        $validated = $request->validate(['locale' => ['nullable', 'string', Rule::in($locales->codes())]]);

        return new JsonResponse(['data' => $facets->offered((string) ($validated['locale'] ?? $locales->defaultCode()))]);
    }

    /**
     * How many products a filter passes now, before the third layer and «only in stock», which need
     * the products themselves (§5). `feed` is the one whose exclusions count.
     */
    public function count(Request $request, FeedSelection $selection, Locales $locales): JsonResponse
    {
        $validated = $request->validate([
            'feed' => ['nullable', 'integer'],
            'filter' => ['present', 'array'],
            'locale' => ['nullable', 'string', Rule::in($locales->codes())],
        ]);

        $feed = isset($validated['feed']) ? Feed::query()->findOrFail((int) $validated['feed']) : new Feed;
        $feed->filter = $validated['filter'];
        $feed->locale = (string) ($validated['locale'] ?? $feed->locale ?? $locales->defaultCode());

        try {
            return new JsonResponse(['data' => [
                'selected' => $selection->query($feed)->count(),
                'excluded' => $feed->exists ? $selection->excluded($feed) : 0,
            ]]);
        } catch (FeedFailure $failure) {
            throw ValidationException::withMessages(['filter' => [$failure->getMessage()]]);
        }
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
        [$feed, $products] = $this->tried($request);

        return new JsonResponse(['data' => $trial->preview($feed, $products)->toArray()]);
    }

    /** The values a form renders with by itself as it is typed in (§8.3). */
    public function sample(Request $request, FeedTrial $trial): JsonResponse
    {
        [$feed, $products] = $this->tried($request);

        return new JsonResponse(['data' => $trial->values($feed, $products)]);
    }

    /**
     * @return array{Feed, list<int>}
     */
    private function tried(Request $request): array
    {
        $validated = Validator::make($this->form->cleaned($request->all()), [
            'feed' => ['sometimes', 'nullable', 'integer'],
            'product_ids' => ['sometimes', 'array', 'max:'.self::NAMED],
            'product_ids.*' => ['integer'],
        ])->validate();

        $feed = isset($validated['feed']) ? Feed::query()->findOrFail((int) $validated['feed']) : new Feed;
        $this->form->fill($feed, $request->all());

        return [$feed, array_map(intval(...), $validated['product_ids'] ?? [])];
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
