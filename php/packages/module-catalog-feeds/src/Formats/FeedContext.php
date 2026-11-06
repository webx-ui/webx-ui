<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Formats;

use WebxUi\CatalogFeeds\Models\Feed;

/**
 * What a class knows when it opens and closes a file: the feed, and what a head and a foot may say
 * (§4.6 of the feeds spec) by the same names, `shop.name`, `products_count`, `categories` and the rest.
 */
final readonly class FeedContext
{
    /**
     * @param  array<string, string|list<array<string, string>>>  $values
     */
    public function __construct(public Feed $feed, public array $values) {}
}
