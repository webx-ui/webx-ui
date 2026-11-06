<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;

/**
 * The journal of a feed (§7.6, §11 of the feeds spec): its builds, what one of them left out, and
 * the key's reissues, which change what a marketplace has to be told as much as a build does.
 */
final class FeedBuildController
{
    public function __construct(private readonly FeedBuildResource $resource) {}

    public function index(Request $request, int $feed): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $found->builds()->orderByDesc('id')->paginate((int) ($validated['per_page'] ?? 20));

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(fn (FeedBuild $build): array => $this->resource->row($build, $found), $page->items()),
            'reissues' => $this->resource->reissues($found),
        ]);
    }

    public function show(int $feed, int $build): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);
        $row = $found->builds()->findOrFail($build);

        return new JsonResponse(['data' => [...$this->resource->row($row, $found), 'rejects' => $this->resource->rejects($row, $found)]]);
    }

    public function rebuild(FeedBuilder $builder, int $feed): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);
        $build = $builder->start($found);

        if (! $build instanceof FeedBuild) {
            throw ValidationException::withMessages(['is_active' => [(string) __('webx-catalog-feeds::errors.inactive')]]);
        }

        return new JsonResponse(['data' => $this->resource->row($build, $found)], 202);
    }
}
