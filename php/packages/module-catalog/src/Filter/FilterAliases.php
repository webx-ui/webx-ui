<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Filter;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The old spellings of the filter's addresses (§4.2 of the properties spec).
 *
 * A facet whose code or value slug changes says so here, and an address written the old way keeps
 * working: the serializer asks only when it cannot read a segment the live way, builds the state
 * from the target, and the storefront's "one spelling" rule answers 301 on the canonical address.
 * A target of null drops the segment — a deleted property's `/laptops/color_black` leads to
 * `/laptops`.
 *
 * Chains do not pile up: an alias to `B` recorded while `B` itself becomes `C` is rewritten to lead
 * to `C`, so every old spelling is one redirect away. Written by the facets themselves: the
 * properties (code, slug, merge, delete) and the brands (slug).
 */
final class FilterAliases
{
    /** `old` is a facet's code in an address; `target` its new code. */
    public const CODE = 'code';

    /** `old` is a value's slug in an address; `target` the facet's value it now means — an id. */
    public const VALUE = 'value';

    private const TABLE = 'catalog_filter_aliases';

    /**
     * `$old` in `$locale` now means `$target`; null drops the segment (or, for a value, the value).
     */
    public function record(string $facetKey, string $locale, string $kind, string $old, ?string $target): void
    {
        if ($old === '' || $old === $target) {
            return;
        }

        DB::transaction(function () use ($facetKey, $locale, $kind, $old, $target): void {
            // One meaning per spelling: the latest. A code is one among all the facets of a
            // language; a value slug is one within its facet.
            $this->spelling($locale, $kind, $old, $facetKey)->delete();

            DB::table(self::TABLE)->insert([
                'facet_key' => $facetKey,
                'locale' => $locale,
                'kind' => $kind,
                'old' => $old,
                'target' => $target,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // A value's alias leads to an id, which does not change, so only codes chain: what led
            // to the old code leads to the new one, and a code renamed back to what it was no
            // longer needs an alias for it.
            if ($kind === self::CODE) {
                DB::table(self::TABLE)
                    ->where('facet_key', $facetKey)
                    ->where('kind', $kind)
                    ->where('locale', $locale)
                    ->where('target', $old)
                    ->update(['target' => $target, 'updated_at' => Carbon::now()]);

                if ($target !== null) {
                    $this->spelling($locale, $kind, $target)->delete();
                }
            }
        });
    }

    /**
     * Every alias that led to `$from` leads to `$to` now, in every language — a value merged into
     * another, or deleted (null).
     */
    public function retarget(string $facetKey, string $kind, string $from, ?string $to): void
    {
        DB::table(self::TABLE)
            ->where('facet_key', $facetKey)
            ->where('kind', $kind)
            ->where('target', $from)
            ->update(['target' => $to, 'updated_at' => Carbon::now()]);
    }

    /**
     * The facet an old code belonged to, and whether it still leads anywhere; null when the code
     * was never anybody's.
     *
     * @return array{facet: string, target: string|null}|null
     */
    public function code(string $code, string $locale): ?array
    {
        $row = $this->spelling($locale, self::CODE, $code)->orderByDesc('id')->first(['facet_key', 'target']);

        if ($row === null) {
            return null;
        }

        return ['facet' => (string) $row->facet_key, 'target' => $row->target === null ? null : (string) $row->target];
    }

    /**
     * What each old slug of a facet means now: a value, or null — dropped. A slug nobody ever had
     * is left out.
     *
     * @param  list<string>  $slugs
     * @return array<string, string|null>
     */
    public function values(string $facetKey, array $slugs, string $locale): array
    {
        if ($slugs === []) {
            return [];
        }

        $found = [];

        foreach (DB::table(self::TABLE)
            ->where('facet_key', $facetKey)
            ->where('locale', $locale)
            ->where('kind', self::VALUE)
            ->whereIn('old', $slugs)
            ->orderBy('id')
            ->get(['old', 'target']) as $row) {
            $found[(string) $row->old] = $row->target === null ? null : (string) $row->target;
        }

        return $found;
    }

    private function spelling(string $locale, string $kind, string $old, ?string $facetKey = null): Builder
    {
        $query = DB::table(self::TABLE)->where('locale', $locale)->where('kind', $kind)->where('old', $old);

        return $kind === self::VALUE && $facetKey !== null ? $query->where('facet_key', $facetKey) : $query;
    }
}
