<?php

declare(strict_types=1);

namespace WebxUi\Cache\Events;

/**
 * An entry on a versioned key was asked for, and where the answer came from.
 *
 * `memo` is the value this request already held — the store never hears of it, so without this
 * event such a read cannot be counted at all; `store` is a hit of the store, `miss` a value that
 * had to be computed (or was not there for `get()`). Sent only when somebody listens: the debug
 * bar does, and a site without it pays one check of an array.
 */
final readonly class GroupRead
{
    public const MEMO = 'memo';

    public const STORE = 'store';

    public const MISS = 'miss';

    /**
     * @param  list<string>  $depends
     */
    public function __construct(
        public string $name,
        public array $depends,
        public string $source,
    ) {}
}
