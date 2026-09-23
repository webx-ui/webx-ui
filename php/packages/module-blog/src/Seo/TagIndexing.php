<?php

declare(strict_types=1);

namespace WebxUi\Blog\Seo;

use WebxUi\Blog\Models\Tag;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Panel\UrlRuleSource;

/**
 * Whether a tag page is in the index, and why (§12).
 *
 * Three answers rather than two, and that is the whole reason this is a class. A tag is out of
 * the index by default; an editor opens it either by clearing the flag on the tag or by writing
 * a rule in `seo_urls` for its address. The second one has to work — the rule was written
 * *because* this page is wanted — and it has to be visible in the panel, or the editor who
 * wrote it spends an afternoon looking at a row that says `noindex` and disagreeing with it.
 *
 * One place for the question so that the rendered page, the column on the tags screen and the
 * filter beside it can never give different answers to it.
 */
final class TagIndexing
{
    public function __construct(private readonly UrlRuleSource $rules) {}

    /** One of {@see Tag::INDEXING_OPEN}, {@see Tag::INDEXING_RULE}, {@see Tag::INDEXING_NOINDEX}. */
    public function state(Tag $tag, ?string $url = null): string
    {
        if (! $tag->noindex) {
            return Tag::INDEXING_OPEN;
        }

        return $this->rules->hasRuleFor($url ?? $this->addressOf($tag))
            ? Tag::INDEXING_RULE
            : Tag::INDEXING_NOINDEX;
    }

    /**
     * What the page should say about robots, or null when it should say nothing.
     *
     * Null rather than `index,follow`: saying nothing is what leaves the decision to whatever
     * the site says in its defaults, and a tag that is simply not excluded has no opinion of
     * its own about the matter.
     */
    public function robots(Tag $tag, ?string $url = null): ?string
    {
        return $this->state($tag, $url) === Tag::INDEXING_NOINDEX ? Tag::ROBOTS_NOINDEX : null;
    }

    /** The address a rule would have been written for: path and language prefix, no host. */
    private function addressOf(Tag $tag): string
    {
        return UrlNormaliser::normalise($tag->url());
    }
}
