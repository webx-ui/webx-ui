<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

/**
 * The stock statuses of a site, for whoever maps them without knowing the stock satellite: a feed
 * says what each one is called on a marketplace (§6.1 of the feeds spec). Bound only by the
 * satellite, so an unbound container means a site with no statuses.
 */
interface StockStatuses
{
    /**
     * @return list<array{code: string, title: string, is_purchasable: bool}>
     */
    public function all(string $locale): array;
}
