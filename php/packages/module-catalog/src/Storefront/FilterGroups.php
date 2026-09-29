<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Engine\FacetResult;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\TreeFacet;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Models\Category;

/**
 * The filter of a page as links (§10.2): every value with its count and a ready address, a range
 * with its ends and a form that works without a script, and a way back to nothing chosen.
 *
 * Two passes, so that every address of the page is built in one call — the rewriters are
 * prepared once (§7.7): the first pass lists what each link would choose, the second hangs the
 * addresses on the options.
 */
final class FilterGroups
{
    /** @var list<FilterState> */
    private array $states = [];

    public function __construct(private readonly FilterUrls $urls) {}

    /**
     * The groups, the way back to nothing chosen, and the page's own address in the registry's
     * spelling — asked in the same pass, so the handler's "is this the one spelling" costs the
     * rewriters nothing more.
     *
     * @return array{groups: list<FilterGroup>, reset: string|null, path: string}
     */
    public function build(FilterContext $context, FilterState $state, CatalogResult $result): array
    {
        $this->states = [];
        $self = $this->add($state);
        $plans = [];

        foreach ($context->facets as $facet) {
            $counted = $result->facet($facet->key());

            if ($counted !== null) {
                $plans[] = $this->plan($context, $state, $facet, $counted);
            }
        }

        $reset = $state->isEmpty() ? null : $this->add(FilterState::empty());
        $paths = $this->urls->paths($context, $this->states);
        $urls = array_map(fn (string $path): string => $this->urls->absolute($context, $path), $paths);
        $groups = [];

        foreach ($plans as $plan) {
            $group = $this->assemble($context, $plan, $urls);

            if (! $group->isEmpty()) {
                $groups[] = $group;
            }
        }

        return ['groups' => $groups, 'reset' => $reset === null ? null : $urls[$reset], 'path' => $paths[$self]];
    }

    /**
     * What one facet will draw, with its links as positions in the list of states.
     *
     * @return array<string, mixed>
     */
    private function plan(FilterContext $context, FilterState $state, Facet $facet, FacetResult $counted): array
    {
        $key = $facet->key();

        if ($facet->kind() === FacetKind::Range) {
            $chosen = $state->get($key);

            return [
                'facet' => $facet,
                'range' => [
                    'min' => $counted->min,
                    'max' => $counted->max,
                    'from' => $chosen?->min,
                    'to' => $chosen?->max,
                ],
                'without' => $this->add($state->without($key)),
                'chosen' => $chosen !== null,
            ];
        }

        if ($facet->kind() === FacetKind::Toggle) {
            $on = $state->has($key);

            return [
                'facet' => $facet,
                'options' => $this->withLinks([[
                    'value' => '1',
                    'label' => $facet->label(),
                    'count' => $counted->count,
                    'selected' => $on,
                    'target' => $on ? $state->without($key) : $state->with($key, FacetValue::of(['1'])),
                    'parent' => null,
                ]]),
            ];
        }

        $chosen = $state->get($key)->values ?? [];

        // On a category page the category facet is the way down: the children of this category,
        // each a move to its own page with the rest of the choice kept (§8.3 of the architecture).
        if ($facet instanceof CategoryFacet && $context->context === FilterContext::CATEGORY && $context->category !== null) {
            $children = Category::query()->visible()->where('parent_id', $context->category->getKey())->orderBy('lft')->get();
            $options = [];

            foreach ($children as $child) {
                $id = (string) $child->id;
                $options[] = [
                    'value' => $id,
                    'label' => $child->displayName($context->locale),
                    'count' => $counted->countOf($id),
                    'selected' => false,
                    'target' => $state->with($key, FacetValue::of([$id])),
                    'parent' => null,
                ];
            }

            return ['facet' => $facet, 'options' => $this->withLinks($options)];
        }

        $values = array_keys(array_filter($counted->counts, static fn (int $count): bool => $count > 0));
        $values = array_values(array_unique([...array_map('strval', $values), ...$chosen]));

        if ($facet instanceof CategoryFacet) {
            $values = Category::query()->visible()->whereKey(array_map('intval', $values))
                ->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        }

        $labels = $facet->labels($values, $context->locale);
        $parents = $facet instanceof TreeFacet ? $facet->parents($values) : [];
        $ordered = $parents !== [] ? array_keys($parents) : $values;

        if ($parents === []) {
            usort($ordered, static fn (string $a, string $b): int => strnatcasecmp($labels[$a] ?? $a, $labels[$b] ?? $b));
        }

        $options = [];

        foreach ($ordered as $value) {
            $value = (string) $value;
            $options[] = [
                'value' => $value,
                'label' => $labels[$value] ?? $value,
                'count' => $counted->countOf($value),
                'selected' => in_array($value, $chosen, true),
                'target' => $state->toggle($key, $value),
                'parent' => $parents[$value] ?? null,
            ];
        }

        return ['facet' => $facet, 'options' => $this->withLinks($options)];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    private function withLinks(array $options): array
    {
        foreach ($options as $i => $option) {
            /** @var FilterState $target */
            $target = $option['target'];
            $options[$i]['link'] = $this->add($target);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<string>  $urls
     */
    private function assemble(FilterContext $context, array $plan, array $urls): FilterGroup
    {
        /** @var Facet $facet */
        $facet = $plan['facet'];

        if (isset($plan['range'])) {
            /** @var array{min: float|null, max: float|null, from: float|null, to: float|null} $range */
            $range = $plan['range'];
            $without = $urls[$plan['without']];

            return new FilterGroup(
                $facet,
                range: [...$range, 'action' => $without, 'field' => 'range['.$facet->code().']'],
                resetUrl: $plan['chosen'] ? $without : null,
            );
        }

        $flat = [];

        /** @var array<string, mixed> $option */
        foreach ($plan['options'] ?? [] as $option) {
            /** @var FilterState $target */
            $target = $option['target'];
            $count = (int) $option['count'];
            $selected = (bool) $option['selected'];
            $index = $option['link'] ?? null;

            $flat[] = [
                'parent' => $option['parent'] ?? null,
                'option' => new FilterOption(
                    value: (string) $option['value'],
                    label: (string) $option['label'],
                    count: $count,
                    // Nothing to show behind it: grey, and no link — unless it is chosen, where
                    // the link is the way to take it off.
                    url: ($count > 0 || $selected) && is_int($index) ? $urls[$index] : null,
                    selected: $selected,
                    nofollow: ! $this->urls->indexable($context, $target) || $count === 0,
                ),
            ];
        }

        return new FilterGroup($facet, options: $this->nest($flat));
    }

    /**
     * A flat list with parents into a tree; a value whose parent is not drawn is a root.
     *
     * @param  list<array{parent: string|null, option: FilterOption}>  $flat
     * @return list<FilterOption>
     */
    private function nest(array $flat, ?string $parent = null): array
    {
        $present = array_map(static fn (array $row): string => $row['option']->value, $flat);
        $level = [];

        foreach ($flat as $row) {
            $own = $row['parent'] !== null && in_array($row['parent'], $present, true) ? $row['parent'] : null;

            if ($own !== $parent) {
                continue;
            }

            $option = $row['option'];
            $level[] = new FilterOption(
                $option->value,
                $option->label,
                $option->count,
                $option->url,
                $option->selected,
                $option->nofollow,
                $this->nest($flat, $option->value),
            );
        }

        return $level;
    }

    private function add(FilterState $state): int
    {
        $this->states[] = $state;

        return count($this->states) - 1;
    }
}
