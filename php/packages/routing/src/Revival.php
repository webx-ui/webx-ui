<?php

declare(strict_types=1);

namespace WebxUi\Routing;

/**
 * What a restore did with the former addresses of an entity: the ones that lead to it again,
 * and the ones that could not, because another entity took the path while it was in the bin or
 * it has no address in that language any more.
 *
 * Paths with a leading slash and no language prefix, as the panel prints them.
 */
final class Revival
{
    /**
     * @param  list<string>  $restored
     * @param  list<string>  $dropped
     */
    public function __construct(
        public readonly array $restored = [],
        public readonly array $dropped = [],
    ) {}

    public function merge(self $other): self
    {
        return new self([...$this->restored, ...$other->restored], [...$this->dropped, ...$other->dropped]);
    }
}
