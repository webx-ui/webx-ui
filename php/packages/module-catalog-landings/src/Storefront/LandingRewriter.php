<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Storefront;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrlRewriter;
use WebxUi\Catalog\Filter\RewrittenUrl;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\Routing\Models\Route;

/**
 * The address of a set a landing holds (§5 of the landings spec): every link of the filter that
 * chooses the set points at the landing, and the category's spelling of it is a 301 there.
 *
 * A landing stands on a base — a category, or the whole catalogue — so a state is looked up among
 * the landings of the page it would be drawn on: on a category's page, the category, or the one
 * below it the state moves to (a subcategory chosen is a move, §8.3 of the architecture); on the
 * root of the catalogue, the landings without a base. A brand's page and the search have none.
 *
 * Among them, the largest that covers the state: each of its facets is chosen, and chosen
 * exactly — the same values, the same range. «Contains» would not do: with Apple and Dell chosen,
 * the Apple landing is not the base, or `/laptops-apple/brand_dell/` would read as both. Ties go to
 * the smaller position, then the smaller id. What the landing does not fix is the rest, written
 * after it.
 *
 * {@see prepare()} reads the published landings of every base the states lead to in one query;
 * the core prepares once per call that builds links, so a page asks once for its filter and once
 * for its own address.
 */
final class LandingRewriter implements FilterUrlRewriter
{
    /** @var array<string, list<array{landing: Landing, state: FilterState, size: int}>> base → its landings */
    private array $landings = [];

    /** @var array<string, true> the bases the last prepare was asked about, with landings or without */
    private array $prepared = [];

    private string $locale = '';

    public function __construct(private readonly Facets $facets) {}

    public function prepare(FilterContext $context, array $states): void
    {
        $this->landings = [];
        $this->prepared = [];
        $this->locale = $context->locale;

        if (! in_array($context->context, [FilterContext::CATEGORY, FilterContext::ROOT], true)) {
            return;
        }

        $bases = [];

        foreach ($states as $state) {
            $bases[$this->base($context, $state)] = true;
        }

        $root = isset($bases['root']);
        $ids = array_map('intval', array_keys(array_filter($bases, static fn (string|int $key): bool => $key !== 'root', ARRAY_FILTER_USE_KEY)));
        $this->prepared = array_fill_keys(array_map('strval', array_keys($bases)), true);

        // Once a request per base: the core prepares the rewriters for the filter's links and
        // again for the page's own address, and the bases of the page are the same each time.
        $memo = 'landings.bases.'.$context->locale;
        $wanted = [...($root ? ['root'] : []), ...array_map('strval', $ids)];
        /** @var array<string, list<Landing>> $read base → its landings, for every base asked so far */
        $read = RequestMemo::remember($memo, static fn (): array => []);
        $missing = array_values(array_diff($wanted, array_keys($read)));
        $root = in_array('root', $missing, true);
        $ids = array_map('intval', array_filter($missing, static fn (string $base): bool => $base !== 'root'));

        $found = $missing === [] ? new Collection : Landing::query()
            ->visible($context->locale)
            // Of the base only its bounds, which is all `stands()` compares.
            ->with(['category:id,lft,rgt', 'routes' => static fn ($routes) => $routes->where('locale', $context->locale)->where('kind', Route::CANONICAL)])
            ->where(static function ($where) use ($root, $ids): void {
                $where->whereIn('category_id', $ids === [] ? [0] : $ids);

                if ($root) {
                    $where->orWhereNull('category_id');
                }
            })
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($missing as $base) {
            $read[$base] = [];
        }

        foreach ($found as $landing) {
            $read[$landing->category_id === null ? 'root' : (string) $landing->category_id][] = $landing;
        }

        RequestMemo::put($memo, $read);

        foreach ($wanted as $base) {
            foreach ($read[$base] as $landing) {
                $state = $landing->set()->state($this->facets);

                if ($state->isEmpty()) {
                    continue;
                }

                $this->landings[$base][] = [
                    'landing' => $landing,
                    'state' => $state,
                    'size' => count($state->all()),
                ];
            }
        }
    }

    /**
     * The landings of a base the last {@see prepare()} read, with their canonical rows — for a
     * list on the same page that would otherwise read them again — or null when the base was
     * not among the page's.
     *
     * @return Collection<int, Landing>|null
     */
    public function loadedFor(?int $categoryId): ?Collection
    {
        $base = $categoryId === null ? 'root' : (string) $categoryId;

        if (! isset($this->prepared[$base])) {
            return null;
        }

        return new Collection(array_map(static fn (array $row): Landing => $row['landing'], $this->landings[$base] ?? []));
    }

    public function rewrite(FilterContext $context, FilterState $state): ?RewrittenUrl
    {
        if ($this->landings === [] || $context->locale !== $this->locale) {
            return null;
        }

        $base = $this->base($context, $state);
        $chosen = $base === 'root' ? $state : $state->without(CategoryFacet::KEY);
        $best = null;

        foreach ($this->landings[$base] ?? [] as $candidate) {
            if (! $this->stands($context, $candidate['landing'])) {
                return null;
            }

            if (($best === null || $candidate['size'] > $best['size']) && $this->covers($candidate['state'], $chosen)) {
                $best = $candidate;
            }
        }

        if ($best === null) {
            return null;
        }

        $route = $best['landing']->routes->first();

        if (! $route instanceof Route) {
            return null;
        }

        $rest = $base === 'root' ? $state : $this->restOnCategory($context, $state);

        foreach (array_keys($best['state']->all()) as $key) {
            $rest = $rest->without($key);
        }

        $count = $best['landing']->products_count;

        return new RewrittenUrl($route->path, $rest, $count === null || $count > 0);
    }

    /**
     * The base a state would be drawn on: `root`, or a category's id. On a category's page, one
     * category chosen below it is a move there; anything else stays on the page's category, and
     * a choice of several categories is part of the rest.
     */
    private function base(FilterContext $context, FilterState $state): string
    {
        if ($context->context === FilterContext::ROOT || $context->category === null) {
            return 'root';
        }

        $here = (string) $context->category->getKey();
        $chosen = $state->get(CategoryFacet::KEY)?->without($here);

        if ($chosen !== null && count($chosen->values) === 1) {
            return $chosen->values[0];
        }

        return $here;
    }

    /**
     * What is left of the category's choice once the page stands on its base: nothing when the
     * state moved to a category below, the choice of several otherwise.
     */
    private function restOnCategory(FilterContext $context, FilterState $state): FilterState
    {
        $chosen = $state->get(CategoryFacet::KEY);

        if ($chosen === null || $context->category === null) {
            return $state;
        }

        $chosen = $chosen->without((string) $context->category->getKey());

        return count($chosen->values) <= 1 ? $state->without(CategoryFacet::KEY) : $state->with(CategoryFacet::KEY, $chosen);
    }

    private function covers(FilterState $set, FilterState $state): bool
    {
        foreach ($set->all() as $key => $value) {
            $chosen = $state->get($key);

            if ($chosen === null || ! $chosen->equals($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the landing's base is where the state is drawn: the page's category, or one below
     * it — a single category chosen above or beside it is not a move but a filter, and no
     * landing's. Read from the category loaded with the landing, no query.
     */
    private function stands(FilterContext $context, Landing $landing): bool
    {
        $here = $context->category;
        $base = $landing->category;

        if ($context->context === FilterContext::ROOT || $here === null) {
            return $landing->category_id === null;
        }

        return $base instanceof Category
            && ($base->is($here) || ($base->lft > $here->lft && $base->rgt < $here->rgt));
    }
}
