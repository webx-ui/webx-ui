<?php

declare(strict_types=1);

namespace WebxUi\Menu\Rendering;

/**
 * One item of a menu as a template reads it (§8).
 *
 * Everything an item needed a query for has already been answered by the time one of these
 * exists: the label, the address, whether the site would show the thing it points at. What is
 * left is the part that cannot be cached, because it is different on every page — whether this
 * item is the one the visitor is standing on.
 */
final class MenuLink
{
    /**
     * @param  string|null  $url  Null for an item that goes nowhere: a heading, a separator.
     * @param  string|null  $rel  The attribute as it is printed, `noopener noreferrer` included.
     * @param  string|null  $path  This item's path on our own site, or null when it is somebody
     *                             else's. Highlighting compares this and nothing else.
     * @param  string  $current  Where the visitor is standing, in the same spelling.
     */
    public function __construct(
        public string $label,
        public ?string $url,
        public MenuTree $children,
        public bool $isHeading = false,
        public string $variant = 'link',
        public bool $newTab = false,
        public ?string $rel = null,
        private readonly ?string $path = null,
        private readonly string $current = '',
    ) {}

    /** The visitor is on this exact page. */
    public function isCurrent(): bool
    {
        return $this->path !== null && $this->path === $this->current;
    }

    /**
     * The visitor is here, or somewhere below here (§6).
     *
     * The home page is the exception to the second half of that rule, and it has to be: its
     * path is the empty string, which is a prefix of every address on the site. The symptom of
     * getting this wrong is two highlighted items, which nobody reports for a month.
     */
    public function isActive(): bool
    {
        if ($this->isCurrent()) {
            return true;
        }

        if ($this->path !== null && $this->path !== '' && str_starts_with($this->current, $this->path.'/')) {
            return true;
        }

        return $this->children->contains(static fn (MenuLink $child): bool => $child->isActive());
    }

    public function hasChildren(): bool
    {
        return $this->children->isNotEmpty();
    }

    /**
     * `href`, `target` and `rel` in one array, for a template that would rather write
     * `@foreach ($item->attrs() as $name => $value)` than three conditionals.
     *
     * @return array<string, string>
     */
    public function attrs(): array
    {
        $attrs = [
            'href' => $this->url,
            'target' => $this->newTab ? '_blank' : null,
            'rel' => $this->rel,
        ];

        return array_filter($attrs, static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
