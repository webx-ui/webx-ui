<?php

declare(strict_types=1);

namespace WebxUi\Admin\Events;

/**
 * Stored content was rewritten in the database directly — around the models, so none of their
 * events fired — and whatever a module caches of it is stale now.
 *
 * The library's «Convert to WebP» is the first to say it: `<uuid>.jpg` became `<uuid>.webp`
 * in every text and JSON column at once. A module that caches content listens and lets go of its
 * own caches; the one that rewrote does not have to know which caches there are, or whose.
 */
final readonly class StoredContentRewritten
{
    /**
     * @param  array<string, string>  $replacements  what was replaced → what with
     * @param  list<string>  $tables  the tables a row changed in, without the prefix
     */
    public function __construct(
        public array $replacements,
        public array $tables = [],
    ) {}
}
