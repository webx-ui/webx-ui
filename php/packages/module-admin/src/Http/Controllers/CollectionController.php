<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Relations\RelationTargets;

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
    public function __construct(
        private readonly CollectionSources $sources,
        private readonly RelationTargets $targets,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::data(array_map(fn (CollectionSource $source): array => [
            'key' => $source->key(),
            'title' => $source->title(),
            'categories' => $source->categories(),
            'markup' => $source->supportsMarkup(),
            'relations' => $this->relations($source),
        ], $this->sources->allowed($request->user())));
    }

    /**
     * What the source's records can be filtered by relation to (§3.6 of the recipes spec): only
     * the kinds a module on this site answers for — a filter by services on a site without them
     * is a picker of nothing.
     *
     * @return list<array{key: string, title: string}>
     */
    private function relations(CollectionSource $source): array
    {
        $relations = [];

        foreach ($source->relations() as $key) {
            $target = $this->targets->find($key);

            if ($target !== null) {
                $relations[] = ['key' => $key, 'title' => $target->label()];
            }
        }

        return $relations;
    }
}
