<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Catalog;

use Illuminate\Support\Carbon;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\Localization\Locales;
use WebxUi\Seo\Sitemap\Sitemap;

/**
 * `products_count` (§6.5 of the landings spec): how many products the storefront shows on a plain
 * landing, asked of the engine the way the page asks it, only for the total.
 *
 * A base with no products at all answers for every landing on it in one query. A count that
 * crosses zero moves the sitemap on: an empty landing is `noindex` and leaves it (decision 9).
 *
 * The number is written past the model — a recount is not an edit, and the journal and the
 * sitemap's own listener stay out of it.
 */
final class LandingCounter
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly LandingRepairs $repairs,
        private readonly Locales $locales,
        private readonly Sitemap $sitemap,
    ) {}

    /** How many products a set shows on a base, without saving anything: the form's live number. */
    public function count(?int $categoryId, FilterState $state): int
    {
        return $this->catalog->engine()->search($this->query($categoryId, $state))->total;
    }

    /**
     * Every landing, or the ones waiting for a count (`counted_at` null). Returns how many were
     * counted.
     */
    public function recount(bool $all = false): int
    {
        $query = Landing::query();

        if (! $all) {
            $query->whereNull('counted_at');
        }

        $done = 0;
        $crossed = false;

        $query->chunkById(200, function ($landings) use (&$done, &$crossed): void {
            /** @var array<string, bool> $emptyBases */
            $emptyBases = [];

            foreach ($landings as $landing) {
                /** @var Landing $landing */
                $this->repairs->sweep($landing);

                $base = $landing->category_id === null ? 'root' : (string) $landing->category_id;
                $emptyBases[$base] ??= $this->count($landing->category_id, FilterState::empty()) === 0;

                $crossed = $this->write($landing, $emptyBases[$base] ? 0 : $this->count($landing->category_id, $landing->state())) || $crossed;
                $done++;
            }
        });

        if ($crossed) {
            $this->sitemap->refresh();
        }

        return $done;
    }

    /** One landing now: after the form saved it. */
    public function recountOne(Landing $landing): void
    {
        if ($this->write($landing, $this->count($landing->category_id, $landing->state()))) {
            $this->sitemap->refresh();
        }
    }

    /** True when the count crossed zero, either way. */
    private function write(Landing $landing, int $count): bool
    {
        $before = $landing->products_count;

        Landing::withTrashed()->whereKey($landing->getKey())->toBase()->update([
            'products_count' => $count,
            'counted_at' => Carbon::now(),
        ]);

        $landing->forceFill(['products_count' => $count, 'counted_at' => Carbon::now()])->syncOriginal();

        return $before === null ? $count === 0 : ($before === 0) !== ($count === 0);
    }

    private function query(?int $categoryId, FilterState $state): CatalogQuery
    {
        return new CatalogQuery(
            locale: $this->locales->defaultCode(),
            context: $categoryId === null ? FilterContext::ROOT : FilterContext::CATEGORY,
            contextId: $categoryId,
            scope: $categoryId === null ? [] : [CategoryFacet::KEY => FacetValue::of([(string) $categoryId])],
            facets: $state->all(),
            perPage: 1,
        );
    }
}
