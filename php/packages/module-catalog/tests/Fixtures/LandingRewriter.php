<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrlRewriter;
use WebxUi\Catalog\Filter\RewrittenUrl;

/**
 * What `module-catalog-landings` will be: one set of values that has an address of its own.
 */
final class LandingRewriter implements FilterUrlRewriter
{
    public int $prepared = 0;

    /** @var list<int> */
    public array $sizes = [];

    public function __construct(
        private readonly string $facet,
        private readonly string $value,
        private readonly string $path,
    ) {}

    public function prepare(FilterContext $context, array $states): void
    {
        $this->prepared++;
        $this->sizes[] = count($states);
    }

    public function rewrite(FilterContext $context, FilterState $state): ?RewrittenUrl
    {
        if (! $state->has($this->facet, $this->value)) {
            return null;
        }

        $chosen = $state->get($this->facet);
        $rest = $chosen !== null && count($chosen->values) === 1
            ? $state->without($this->facet)
            : $state->with($this->facet, $chosen->without($this->value));

        return new RewrittenUrl($this->path, $rest);
    }
}
