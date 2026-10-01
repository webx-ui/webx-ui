<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Storefront;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Storefront\StorefrontPart;

/**
 * A point of the storefront the landings write into (§6.2, §7 of the landings spec): the strip of
 * recommended products and the collections over a list, the neighbours under it, «In collections»
 * beside a product.
 *
 * What each view needs is worked out from the page or the product the point is given — by the
 * view's composer ({@see LandingViews}), so a site that publishes the view keeps the data — not
 * from the products of a page: the strip and the links are about the page, not its cards.
 */
final class LandingPart implements StorefrontPart
{
    public function __construct(
        private readonly string $point,
        private readonly string $view,
    ) {}

    public function point(): string
    {
        return $this->point;
    }

    public function view(): string
    {
        return $this->view;
    }

    public function prepare(Collection $products): array
    {
        return [];
    }
}
