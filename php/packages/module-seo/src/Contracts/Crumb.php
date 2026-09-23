<?php

declare(strict_types=1);

namespace WebxUi\Seo\Contracts;

/**
 * One step of a trail: what it is called and where it leads.
 *
 * The address is absolute, the way `HasUrl::url()` gives it. It may be null for a step that is a
 * heading rather than a page; such a step is printed as text, and its `ListItem` carries no
 * `item`.
 */
final readonly class Crumb
{
    public function __construct(
        public string $title,
        public ?string $url = null,
    ) {}
}
