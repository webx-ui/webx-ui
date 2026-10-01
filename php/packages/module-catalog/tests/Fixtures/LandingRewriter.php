<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrlRewriter;
use WebxUi\Catalog\Filter\RewrittenUrl;

/**
 * What `module-catalog-landings` will be, in two shapes.
 *
 * Built with a facet, a value and a path, it is the first sketch: one value that has an address of
 * its own, wherever it is chosen. With {@see landing()} it holds sets the way the landings spec
 * reads them (§5): the largest set whose every facet the state holds exactly — not "contains", so
 * Apple and Dell are not the Apple landing — on the page whose path it stands on, with the rest of
 * the state after it.
 */
final class LandingRewriter implements FilterUrlRewriter
{
    public int $prepared = 0;

    /** @var list<int> */
    public array $sizes = [];

    /** @var list<array{on: string, set: FilterState, path: string, indexable: bool}> */
    private array $landings = [];

    public function __construct(
        private readonly ?string $facet = null,
        private readonly ?string $value = null,
        private readonly ?string $path = null,
    ) {}

    /**
     * @param  array<string, FacetValue>  $set
     * @param  string  $on  the path of the page the set stands on: a category's
     */
    public function landing(array $set, string $path, bool $indexable = true, string $on = 'laptops'): self
    {
        $this->landings[] = ['on' => $on, 'set' => FilterState::of($set), 'path' => $path, 'indexable' => $indexable];

        return $this;
    }

    public function prepare(FilterContext $context, array $states): void
    {
        $this->prepared++;
        $this->sizes[] = count($states);
    }

    public function rewrite(FilterContext $context, FilterState $state): ?RewrittenUrl
    {
        return $this->covering($context, $state) ?? $this->single($state);
    }

    private function covering(FilterContext $context, FilterState $state): ?RewrittenUrl
    {
        $best = null;

        foreach ($this->landings as $landing) {
            if ($landing['on'] !== $context->path || ! $this->covers($landing['set'], $state)) {
                continue;
            }

            if ($best === null || count($landing['set']->all()) > count($best['set']->all())) {
                $best = $landing;
            }
        }

        if ($best === null) {
            return null;
        }

        $rest = $state;

        foreach (array_keys($best['set']->all()) as $key) {
            $rest = $rest->without($key);
        }

        return new RewrittenUrl($best['path'], $rest, $best['indexable']);
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

    private function single(FilterState $state): ?RewrittenUrl
    {
        if ($this->facet === null || $this->value === null || $this->path === null || ! $state->has($this->facet, $this->value)) {
            return null;
        }

        $chosen = $state->get($this->facet);
        $rest = $chosen !== null && count($chosen->values) === 1
            ? $state->without($this->facet)
            : $state->with($this->facet, $chosen->without($this->value));

        return new RewrittenUrl($this->path, $rest);
    }
}
