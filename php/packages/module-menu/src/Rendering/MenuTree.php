<?php

declare(strict_types=1);

namespace WebxUi\Menu\Rendering;

use Illuminate\Support\Collection;

/**
 * What `menu('header')` hands back: a collection of {@see MenuLink}, nested.
 *
 * Data and not markup, on purpose (§2, decision 12). A component exists too, and is second —
 * every real site has its own markup, and a component that has to be overridden is worse than a
 * collection somebody writes ten lines against.
 *
 * @extends Collection<int, MenuLink>
 */
final class MenuTree extends Collection
{
    /**
     * The same items with the nesting taken out, parents before their children — a footer,
     * usually, or a `<select>` on a phone. The links keep their children; what is flattened is
     * the walk, not the tree.
     */
    public function flat(): self
    {
        $flat = [];

        foreach ($this as $link) {
            $flat[] = $link;

            foreach ($link->children->flat() as $child) {
                $flat[] = $child;
            }
        }

        return new self($flat);
    }

    /** The deepest item the visitor is standing on or inside, if any. */
    public function active(): ?MenuLink
    {
        foreach ($this as $link) {
            if (! $link->isActive()) {
                continue;
            }

            return $link->children->active() ?? $link;
        }

        return null;
    }

    /**
     * Rebuild the links from what the cache holds.
     *
     * The cache keeps arrays rather than these objects: highlighting depends on the page being
     * looked at, so it is worked out here, after the read, on every request.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function hydrate(array $nodes, string $current): self
    {
        $links = [];

        foreach ($nodes as $node) {
            /** @var list<array<string, mixed>> $children */
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];

            $links[] = new MenuLink(
                label: (string) ($node['label'] ?? ''),
                url: self::text($node['url'] ?? null),
                children: self::hydrate($children, $current),
                isHeading: (bool) ($node['heading'] ?? false),
                variant: (string) ($node['variant'] ?? 'link'),
                newTab: (bool) ($node['new_tab'] ?? false),
                rel: self::text($node['rel'] ?? null),
                path: self::text($node['path'] ?? null),
                current: $current,
            );
        }

        return new self($links);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
