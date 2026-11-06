<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * The first two layers of what goes into a feed (§5 of the feeds spec): the filter and the manual
 * exclusions, one query over what the site shows. The third layer needs the products themselves and
 * is {@see FeedChecks}.
 *
 * A query to the database and not to a search engine: a build walks by the cursor and needs no
 * index. The conditions are the facets' own, so a choice means what it means in the catalogue.
 *
 * A filter that names a facet nobody has fails the build: a filter dropped in silence would send
 * products the person took out of the feed, and yesterday's file is better than that. A value nobody
 * knows selects nothing for the same reason.
 */
final readonly class FeedSelection
{
    public const EXCLUSIONS = 'catalog_feed_exclusions';

    private const CATEGORY = 'category';

    private const BRAND = 'brand';

    private const PRICE = 'price';

    private const LABEL = 'label';

    public function __construct(private Facets $facets) {}

    /**
     * @return Builder<Product>
     *
     * @throws FeedFailure
     */
    public function query(Feed $feed): Builder
    {
        return $this->filtered($feed)->whereNotExists(static function (QueryBuilder $excluded) use ($feed): void {
            $excluded->selectRaw('1')
                ->from(self::EXCLUSIONS)
                ->where(self::EXCLUSIONS.'.feed_id', $feed->id)
                ->whereColumn(self::EXCLUSIONS.'.product_id', 'catalog_products.id');
        });
    }

    /**
     * How many products the filter passes and a person took out.
     *
     * @throws FeedFailure
     */
    public function excluded(Feed $feed): int
    {
        return $this->filtered($feed)
            ->whereIn('catalog_products.id', static function (QueryBuilder $excluded) use ($feed): void {
                $excluded->select('product_id')->from(self::EXCLUSIONS)->where('feed_id', $feed->id);
            })
            ->count();
    }

    /**
     * Which of these a person took out of this feed.
     *
     * @param  list<int>  $products
     * @return list<int>
     */
    public function excludedAmong(Feed $feed, array $products): array
    {
        return DB::table(self::EXCLUSIONS)
            ->where('feed_id', $feed->id)
            ->whereIn('product_id', $products)
            ->pluck('product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return Builder<Product>
     */
    private function filtered(Feed $feed): Builder
    {
        $query = Product::query()->visible();
        $filter = $feed->filter;
        $facets = $this->facets->selectable($feed->locale);

        $categories = (array) ($filter['categories'] ?? []);
        $this->choose($query, $facets, self::CATEGORY, FacetValue::of((array) ($categories['include'] ?? [])));
        $this->leaveOut($query, $facets, FacetValue::of((array) ($categories['exclude'] ?? [])));

        $this->choose($query, $facets, self::BRAND, FacetValue::of((array) ($filter['brands'] ?? [])));
        $this->choose($query, $facets, self::PRICE, self::value($filter['price'] ?? []));
        $this->labels($query, $facets, (array) ($filter['labels'] ?? []), $feed->locale);

        foreach ((array) ($filter['properties'] ?? []) as $key => $value) {
            $this->choose($query, $facets, (string) $key, self::value($value));
        }

        return $query;
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, Facet>  $facets
     */
    private function choose(Builder $query, array $facets, string $key, FacetValue $value): void
    {
        if (! $value->isEmpty()) {
            $facet = self::facet($facets, $key);
            $facet->applySql($query, $facet->normalise($value));
        }
    }

    /**
     * A branch taken out: the same condition, turned inside out.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, Facet>  $facets
     */
    private function leaveOut(Builder $query, array $facets, FacetValue $value): void
    {
        if ($value->isEmpty()) {
            return;
        }

        $inside = Product::query()->select('catalog_products.id');
        $this->choose($inside, $facets, self::CATEGORY, $value);

        $query->whereNotIn('catalog_products.id', $inside);
    }

    /**
     * A filter holds the codes of labels, which outlive a label's id on another stand.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, Facet>  $facets
     * @param  array<array-key, mixed>  $codes
     */
    private function labels(Builder $query, array $facets, array $codes, string $locale): void
    {
        if ($codes === []) {
            return;
        }

        $facet = self::facet($facets, self::LABEL);
        $known = $facet->resolveSlugs(array_values(array_map(strval(...), $codes)), $locale);

        if ($known === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $this->choose($query, $facets, self::LABEL, FacetValue::of($known));
    }

    /**
     * `{ min, max }` is a range, anything else the values chosen.
     */
    private static function value(mixed $value): FacetValue
    {
        if (is_array($value) && (array_key_exists('min', $value) || array_key_exists('max', $value))) {
            return FacetValue::range(self::end($value['min'] ?? null), self::end($value['max'] ?? null));
        }

        return FacetValue::of(array_map(strval(...), array_values((array) $value)));
    }

    private static function end(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, Facet>  $facets
     */
    private static function facet(array $facets, string $key): Facet
    {
        return $facets[$key] ?? throw FeedFailure::of('filter-facet', ['facet' => $key]);
    }
}
