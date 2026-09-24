<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Http\ApiResponse;

/**
 * What a `wx-collection` field needs to draw itself (§3.3 of the FAQ spec): the sources this
 * administrator may place, each with its name, where its categories answer, and whether it can
 * mark up what it shows.
 *
 * One address for every module, as the link picker has one: the permission rule is written once
 * ({@see CollectionSources::allowed()}) instead of once per section.
 */
final class CollectionController
{
    public function __construct(private readonly CollectionSources $sources) {}

    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::data(array_map(static fn (CollectionSource $source): array => [
            'key' => $source->key(),
            'title' => $source->title(),
            'categories' => $source->categories(),
            'markup' => $source->supportsMarkup(),
        ], $this->sources->allowed($request->user())));
    }
}
