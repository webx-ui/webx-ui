<?php

declare(strict_types=1);

namespace WebxUi\Seo\Targets;

/**
 * What saving an address found out about it: the target, and what happened on the way that the
 * person who typed it should be told — the address redirected somewhere else, or it was an old
 * address of a page that has a new one.
 */
final class Binding
{
    public function __construct(
        public readonly UrlTarget $target,
        /** The address as written, when a redirect replaced it with its target. */
        public readonly ?string $redirectedFrom = null,
        /** The address was an alias: the entity was found through the canonical row it leads to. */
        public readonly bool $viaAlias = false,
    ) {}
}
