<?php

declare(strict_types=1);

namespace WebxUi\Seo\Contracts;

/**
 * What kind of thing a page is to a social network, and the properties that kind carries.
 *
 * Without it a page is `website`, which is right for the home page, a listing, an ordinary page,
 * a service, an event and a press outlet — Open Graph has no current type for any of those, and
 * a wrong one is worse than the general one. The entity that is an article says so here, with
 * its dates, its section and its tags, all from what it already has; an editor types none of it.
 *
 * Two methods because they are asked at different moments: the type on every resolve (the
 * sitemap resolves every address), the properties only when a `<head>` is being printed — they
 * cost the queries for a rubric and the tags.
 */
interface HasOpenGraph
{
    /** `article`, `website`, … — the value of `og:type`. */
    public function openGraphType(): string;

    /**
     * The properties of that type, by their full name: `article:published_time` →
     * `2026-10-07T09:00:00+00:00`, `article:tag` → a list for a property said once per value.
     * Empty values are left out by the printer, so an entity can hand over what it has.
     *
     * @return array<string, string|list<string>|null>
     */
    public function openGraphProperties(string $locale): array;
}
