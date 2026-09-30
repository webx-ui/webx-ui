<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Storefront;

use WebxUi\CatalogProperties\Models\Property;

/**
 * One property of a product as a template shows it (§8.3 of the properties spec): what
 * `$product->properties()` lists, the card's line and the table's row.
 *
 * `value` is the stored one in its type's shape (an id or ids, a number, `true`, a map of
 * languages); `formatted` is it in words — `Black, Grey`, `⌀12 mm`, `Yes`. A reference book's
 * values are in `values` one by one, each with the address of its first level where that page is
 * open (`/laptops/color_black`), and `url` is that address when there is one value.
 */
final class ShownProperty
{
    /**
     * @param  array{id: int, title: string}|null  $group  null without a group, or with a hidden one
     * @param  list<array{id: int, label: string, color: string|null, url: string|null}>  $values
     */
    public function __construct(
        public readonly Property $property,
        public readonly string $code,
        public readonly string $label,
        public readonly mixed $value,
        public readonly string $formatted,
        public readonly ?array $group,
        public readonly array $values = [],
        public readonly ?string $url = null,
    ) {}
}
