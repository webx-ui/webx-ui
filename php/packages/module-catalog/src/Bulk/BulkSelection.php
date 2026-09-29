<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk;

use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\ChosenFacets;
use WebxUi\Catalog\Sorts\Sorts;

/**
 * What a bulk action is done to, as ids, at the moment it starts (§11.4).
 *
 * Two ways to choose: a list of ids — rows ticked in the list — or the list's own query, "all
 * 40 312 the filter finds". The query is asked of the engine that answered the list, so the
 * number the panel showed and the number that runs are the same count; and it is asked once,
 * here, so a product that starts matching the filter while the chunks go is not in the run, and
 * one that stops matching still is.
 */
final class BulkSelection
{
    /** A page of the engine at a time: honest for `SqlEngine`, and inside Manticore's window. */
    private const PAGE = 1000;

    /** More ids than this in a body is somebody who should have sent the query instead. */
    public const MAX_IDS = 10000;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ChosenFacets $facets,
    ) {}

    /**
     * @param  array<string, mixed>  $selection  `{ ids: [..] }` or `{ query: { q, state, facets } }`
     * @param  bool  $trashed  the products in «Deleted» rather than the live ones
     * @return list<int> sorted — the chunks go by id
     *
     * @throws ValidationException
     */
    public function resolve(array $selection, bool $trashed, string $locale): array
    {
        if (is_array($selection['ids'] ?? null)) {
            return $this->ids($selection['ids'], $trashed);
        }

        if (is_array($selection['query'] ?? null)) {
            return $this->query($selection['query'], $trashed, $locale);
        }

        throw ValidationException::withMessages(['selection' => [(string) __('webx-catalog::bulk.errors.selection')]]);
    }

    /**
     * @param  array<mixed>  $given
     * @return list<int>
     */
    private function ids(array $given, bool $trashed): array
    {
        $ids = array_values(array_unique(array_map(intval(...), array_filter($given, is_numeric(...)))));

        if (count($ids) > self::MAX_IDS) {
            throw ValidationException::withMessages(['selection.ids' => [(string) __('webx-catalog::bulk.errors.too-many')]]);
        }

        if ($ids === []) {
            return [];
        }

        $query = $trashed ? Product::onlyTrashed() : Product::query();

        return $this->sorted($query->whereKey($ids)->pluck('id')->all());
    }

    /**
     * @param  array<mixed>  $input
     * @return list<int>
     */
    private function query(array $input, bool $trashed, string $locale): array
    {
        $state = $input['state'] ?? null;
        $state = is_string($state) && in_array($state, [CatalogQuery::STATE_PUBLISHED, CatalogQuery::STATE_UNPUBLISHED, CatalogQuery::STATE_NO_CATEGORY], true) ? $state : null;
        $facets = $this->facets->read(is_array($input['facets'] ?? null) ? $input['facets'] : []);
        $search = trim(is_string($input['q'] ?? null) ? $input['q'] : '');

        $ids = [];
        $page = 1;

        do {
            $result = $this->catalog->engine()->search(new CatalogQuery(
                locale: $locale,
                context: 'panel',
                facets: $facets,
                count: [],
                search: $search,
                sort: Sorts::DEFAULT,
                page: $page,
                perPage: self::PAGE,
                withUnpublished: true,
                onlyTrashed: $trashed,
                state: $state,
            ));

            array_push($ids, ...$result->ids);
            $page++;
        } while (count($result->ids) === self::PAGE && count($ids) < $result->total);

        return $this->sorted($ids);
    }

    /**
     * @param  array<mixed>  $ids
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        sort($ids);

        return $ids;
    }
}
