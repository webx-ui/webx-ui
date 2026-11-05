<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use WebxUi\CatalogFeeds\Models\Feed;

/**
 * A feed kept, and what its trial had to say that did not stop it.
 */
final readonly class FeedSaved
{
    /**
     * @param  list<array{code: string, count: int, message: string|null}>  $warnings
     */
    public function __construct(public Feed $feed, public array $warnings = []) {}
}
