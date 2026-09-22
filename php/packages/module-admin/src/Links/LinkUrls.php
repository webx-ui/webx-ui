<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Localization\Locales;

/**
 * What a stored link points at right now.
 *
 * Right now, and not when it was stored. An entity's address is asked for on every read — a page
 * renamed last week took its menu item with it, and the registry left a redirect behind the old
 * spelling — and the language prefix belongs to the language being read rather than to the one
 * the editor was working in. Both are the same rule the file manager has always followed: what is
 * kept is the record, the address is worked out.
 */
final readonly class LinkUrls
{
    /**
     * `$site` is null on a panel with no address registry, and a hand-written path then travels
     * as it was written.
     */
    public function __construct(
        private LinkSources $sources,
        private Locales $locales,
        private ?SiteUrls $site = null,
    ) {}

    /**
     * What the entity behind an `entity` link is called and where it lives — null for anything
     * else, and for an entity that has since been deleted.
     */
    public function candidate(Link $link, ?string $locale = null): ?LinkCandidate
    {
        if ($link->target !== LinkTarget::Entity || $link->entityType === null || $link->entityId === null) {
            return null;
        }

        $source = $this->sources->find($link->entityType);

        if ($source === null) {
            return null;
        }

        return $source->resolve([$link->entityId], $locale ?? $this->locales->current())[$link->entityId] ?? null;
    }

    /**
     * The entities a whole set of links points at, one query per kind rather than one per link.
     *
     * What a list of links needs and a single field does not: a menu of forty items would
     * otherwise be forty queries to draw, and the screens that read links in bulk are exactly
     * the ones drawn most often.
     *
     * @param  list<Link>  $links
     * @return array<string, array<int, LinkCandidate>> Keyed by morph alias, then by id.
     */
    public function candidates(array $links, ?string $locale = null): array
    {
        $wanted = [];

        foreach ($links as $link) {
            if ($link->target !== LinkTarget::Entity || $link->entityType === null || $link->entityId === null) {
                continue;
            }

            $wanted[$link->entityType][$link->entityId] = $link->entityId;
        }

        $candidates = [];

        foreach ($wanted as $type => $ids) {
            $source = $this->sources->find($type);

            // A link left behind by a module that has since been removed. The value stays where
            // it is — uninstalling a package is not a reason to rewrite somebody's menu — and
            // there is simply nothing to say about it.
            $candidates[$type] = $source === null
                ? []
                : $source->resolve(array_values($ids), $locale ?? $this->locales->current());
        }

        return $candidates;
    }

    /**
     * The address to put in `href`, or null when the link goes nowhere.
     *
     * The anchor is appended here rather than stored in the address, because where the address
     * comes from differs — a page's is looked up, a path is prefixed — and the fragment is the
     * same either way. A link that is an anchor and nothing else points at the page it is
     * printed on, which is what a button inside a block usually means by one.
     */
    public function href(Link $link, ?string $locale = null): ?string
    {
        return $this->hrefWith($link, $this->candidate($link, $locale), $locale);
    }

    /**
     * The same, for a caller that has already resolved the entity in bulk.
     *
     * Apart from {@see href()} rather than an optional argument to it: "no candidate was found"
     * and "no candidate was looked up" are different states, and one parameter cannot say both.
     */
    public function hrefWith(Link $link, ?LinkCandidate $candidate, ?string $locale = null): ?string
    {
        $address = match ($link->target) {
            LinkTarget::Entity => $candidate?->url,
            LinkTarget::Url => $link->url === null ? null : $this->path($link->url, $locale),
            LinkTarget::None => null,
        };

        if ($address === null) {
            return $link->target === LinkTarget::Url && $link->hash !== null ? $link->fragment() : null;
        }

        return $address.$link->fragment();
    }

    private function path(string $url, ?string $locale): string
    {
        return $this->site?->to($url, $locale) ?? $url;
    }
}
