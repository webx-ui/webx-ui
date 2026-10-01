<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

/**
 * The texts the owner of a list prints on its plain page (§10.3 of the landings spec): one above
 * the products, one under the pages. The template asks the owner, not the category, so a landing
 * fills both and a category keeps its one description above — a difference in the data, not in
 * the markup.
 *
 * Rich text, printed as it is; null or empty prints nothing.
 */
interface HasListingTexts
{
    public function textAbove(string $locale): ?string;

    public function textBelow(string $locale): ?string;
}
