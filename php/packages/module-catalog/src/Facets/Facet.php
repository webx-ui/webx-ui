<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Models\Product;

/**
 * Something the catalogue can be filtered by (§7.1, §4.1 of the architecture), registered by the
 * module the feature belongs to: the core's category and price, a satellite's brand or property.
 *
 * A facet does not count. Counts, ranges, and the rule that a facet's own choice does not narrow
 * its own counts — so that choosing Apple still shows Dell beside it — are the engine's, the same
 * for every facet. What a facet tells the engine is where its values are: {@see applySql()} to
 * narrow a query, {@see sqlValues()} to list the pairs the engine groups.
 *
 * Values are strings throughout: an id or a code, the facet's own. The address never carries
 * them — it carries slugs, and {@see slugs()} / {@see resolveSlugs()} translate a page's worth
 * at a time, because a filter draws hundreds of links.
 */
interface Facet
{
    /** The registry's name for it: `price`, `brand`, `p.color`. */
    public function key(): string;

    /** Its name in an address, `[a-z0-9-]`, unique in the registry: `brand` in `brand_apple`. */
    public function code(): string;

    public function kind(): FacetKind;

    public function label(): string;

    /**
     * Whether a page with exactly one value of this facet chosen is open to the index (§8.1 of
     * the architecture). Yes for reference books; a range or a toggle deserves no page of its own.
     */
    public function indexable(): bool;

    /** How the feature lies in an index an engine keeps. */
    public function field(): IndexField;

    /**
     * The words for these values, in one query.
     *
     * @param  list<string>  $values
     * @return array<array-key, string> value → label; a value nobody knows is left out.
     */
    public function labels(array $values, string $locale): array;

    /**
     * @param  list<string>  $values
     * @return array<array-key, string> value → slug, `[a-z0-9-]`, unique within the facet.
     */
    public function slugs(array $values, string $locale): array;

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string> slug → value; a slug nobody knows is left out.
     */
    public function resolveSlugs(array $slugs, string $locale): array;

    /**
     * One spelling of a choice: for a tree, a chosen ancestor takes its chosen descendants in —
     * `material_metal_steel` is `material_metal` said twice (§8.3 of the architecture).
     */
    public function normalise(FacetValue $value): FacetValue;

    /**
     * Narrow the products to those that match the choice.
     *
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void;

    /**
     * Pairs `(product_id, value)` for the products `$products` selects (a query of one column,
     * their ids), which the engine groups: a count per value for terms and trees — a tree lists
     * every ancestor of a product's value as well — the ends for a range, the rows for a toggle.
     */
    public function sqlValues(QueryBuilder $products): QueryBuilder;
}
