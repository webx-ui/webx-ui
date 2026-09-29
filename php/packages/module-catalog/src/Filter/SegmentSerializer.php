<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Filter;

use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Category;

/**
 * The address format of §8.1 of the architecture: a segment per facet, `code_value_value`.
 *
 *     /laptops/brand_apple                 one value — open to the index
 *     /laptops/brand_apple_dell            two values of one facet — closed
 *     /laptops/brand_apple/color_black     a combination — closed
 *     /laptops/price_100-500               a range — closed always
 *
 * `_` is the border because no slug can hold it: category slugs, facet codes and value slugs are
 * `[a-z0-9-]`. So a segment with `_` is a filter and one without is not, and a segment splits in
 * one way only.
 *
 * One spelling: segments in the registry's order of facets, values by slug, a tree's chosen
 * ancestor taking its chosen descendants in. Whoever writes it otherwise gets a 301 — the
 * handler compares what it was asked with what {@see buildMany()} would have written.
 *
 * On a category page, choosing one category below it is not a filter but a move: the address is
 * that category's own, with the rest of the choice after it — `/laptops/category_gaming-laptops`
 * would be a second address of `/gaming-laptops` (§8.3 of the architecture).
 */
final class SegmentSerializer implements FilterSerializer
{
    private const RANGE = '/^(\d+(?:\.\d+)?)?-(\d+(?:\.\d+)?)?$/';

    public function __construct(private readonly Facets $facets) {}

    public function parse(FilterContext $context, string $tail): ?FilterState
    {
        $tail = trim($tail, '/');

        if ($tail === '') {
            return FilterState::empty();
        }

        /** @var array<string, list<string>> $slugs facet key → slugs asked for */
        $slugs = [];
        /** @var array<string, FacetValue> $ranges */
        $ranges = [];

        foreach (explode('/', $tail) as $segment) {
            if (! str_contains($segment, '_')) {
                return null;
            }

            [$code, $rest] = explode('_', $segment, 2);
            $facet = $context->facetByCode($code);
            $parts = explode('_', $rest);

            if ($facet === null || in_array('', $parts, true)) {
                return null;
            }

            if ($facet->kind() === FacetKind::Range) {
                $range = count($parts) === 1 ? $this->range($parts[0]) : null;

                if ($range === null || isset($ranges[$facet->key()])) {
                    return null;
                }

                $ranges[$facet->key()] = $range;

                continue;
            }

            $slugs[$facet->key()] = [...($slugs[$facet->key()] ?? []), ...$parts];
        }

        $chosen = $ranges;

        foreach ($slugs as $key => $asked) {
            $facet = $context->facet($key);
            $asked = array_values(array_unique($asked));
            $resolved = $facet?->resolveSlugs($asked, $context->locale) ?? [];

            if (count($resolved) !== count($asked)) {
                return null;
            }

            $chosen[$key] = FacetValue::of(array_values($resolved));
        }

        return FilterState::of($chosen);
    }

    /**
     * @param  list<FilterState>  $states
     * @return list<string>
     */
    public function buildMany(FilterContext $context, array $states): array
    {
        $states = array_map(fn (FilterState $state): FilterState => $this->normalise($context, $state), $states);
        [$bases, $states] = $this->moves($context, $states);

        $tails = $this->tails($context, $states);
        $paths = [];

        foreach ($tails as $i => $tail) {
            $base = $bases[$i] ?? $context->path;
            $paths[] = trim($base.($tail === '' ? '' : '/'.$tail), '/');
        }

        return $paths;
    }

    /**
     * @param  list<FilterState>  $states
     * @return list<string>
     */
    public function tails(FilterContext $context, array $states): array
    {
        $slugs = $this->slugs($context, $states);
        $tails = [];

        foreach ($states as $state) {
            $segments = [];

            foreach ($this->facets->all() as $facet) {
                $value = $state->get($facet->key());

                if ($value === null || $value->isEmpty()) {
                    continue;
                }

                if ($facet->kind() === FacetKind::Range) {
                    $segments[] = $facet->code().'_'.$this->number($value->min).'-'.$this->number($value->max);

                    continue;
                }

                $written = [];

                foreach ($value->values as $one) {
                    $written[] = $slugs[$facet->key()][$one] ?? $one;
                }

                sort($written, SORT_STRING);
                $segments[] = $facet->code().'_'.implode('_', array_unique($written));
            }

            $tails[] = implode('/', $segments);
        }

        return $tails;
    }

    /**
     * Each facet's own spelling of its choice, and on a category page the category itself taken
     * out of it — it is where the page already stands.
     */
    private function normalise(FilterContext $context, FilterState $state): FilterState
    {
        $chosen = [];

        foreach ($state->all() as $key => $value) {
            $facet = $this->facets->find($key);

            if ($facet === null) {
                continue;
            }

            $value = $facet->normalise($value);

            if ($key === CategoryFacet::KEY && $context->category !== null) {
                $value = $value->without((string) $context->category->getKey());
            }

            $chosen[$key] = $value;
        }

        return FilterState::of($chosen);
    }

    /**
     * On a category page, the states that choose exactly one category under it move to that
     * category's address. The bases of the moved ones, by position, and the states left to write.
     *
     * @param  list<FilterState>  $states
     * @return array{0: array<int, string>, 1: list<FilterState>}
     */
    private function moves(FilterContext $context, array $states): array
    {
        $here = $context->category;

        if ($context->context !== FilterContext::CATEGORY || $here === null) {
            return [[], $states];
        }

        $single = [];

        foreach ($states as $i => $state) {
            $chosen = $state->get(CategoryFacet::KEY);

            if ($chosen !== null && count($chosen->values) === 1) {
                $single[$i] = (int) $chosen->values[0];
            }
        }

        if ($single === []) {
            return [[], $states];
        }

        $below = Category::query()
            ->whereKey(array_values(array_unique($single)))
            ->where('lft', '>', $here->lft)
            ->where('rgt', '<', $here->rgt)
            ->get();

        $paths = [];

        foreach ($below as $category) {
            $slug = $category->getTranslation('slug', $context->locale, false);

            if (is_string($slug) && $slug !== '') {
                $paths[(int) $category->id] = $slug;
            }
        }

        $bases = [];

        foreach ($single as $i => $id) {
            if (isset($paths[$id])) {
                $bases[$i] = $paths[$id];
                $states[$i] = $states[$i]->without(CategoryFacet::KEY);
            }
        }

        return [$bases, $states];
    }

    /**
     * Every slug the states need, one call per facet.
     *
     * @param  list<FilterState>  $states
     * @return array<string, array<string, string>>
     */
    private function slugs(FilterContext $context, array $states): array
    {
        $wanted = [];

        foreach ($states as $state) {
            foreach ($state->all() as $key => $value) {
                foreach ($value->values as $one) {
                    $wanted[$key][$one] = $one;
                }
            }
        }

        $slugs = [];

        foreach ($wanted as $key => $values) {
            $facet = $this->facets->find($key);

            if ($facet instanceof Facet && $facet->kind() !== FacetKind::Range) {
                $slugs[$key] = $facet->slugs(array_values($values), $context->locale);
            }
        }

        return $slugs;
    }

    private function range(string $written): ?FacetValue
    {
        if (preg_match(self::RANGE, $written, $matches) !== 1) {
            return null;
        }

        $min = ($matches[1] ?? '') === '' ? null : (float) $matches[1];
        $max = ($matches[2] ?? '') === '' ? null : (float) $matches[2];

        return $min === null && $max === null ? null : FacetValue::range($min, $max);
    }

    /** `100`, `99.5` — the shortest honest spelling, and nothing for an open end. */
    private function number(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
