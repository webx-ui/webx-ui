<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use WebxUi\Catalog\Facets\SwatchedFacet;

/**
 * One value in the filter as a template draws it (§10.2): a link that is ready, the number
 * beside it, and whether the link is one a search engine should follow. A value that would leave
 * nothing has no link at all — it is drawn grey.
 */
final class FilterOption
{
    /**
     * @param  list<FilterOption>  $children  a tree's values below this one
     * @param  array{color: string|null, image: string|null}|null  $swatch  a colour or a picture before the label ({@see SwatchedFacet})
     */
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly int $count,
        public readonly ?string $url,
        public readonly bool $selected = false,
        public readonly bool $nofollow = false,
        public readonly array $children = [],
        public readonly ?array $swatch = null,
    ) {}

    public function disabled(): bool
    {
        return $this->url === null;
    }
}
