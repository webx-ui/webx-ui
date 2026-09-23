<?php

declare(strict_types=1);

namespace WebxUi\Routing\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether the public site shows this entity. The handler and the sitemap ask the same thing.
 *
 * The registry deliberately does not know (§8.6): a row in `routes` says where an entity would
 * live, not whether anybody may look at it — a draft keeps its address so that publishing it
 * does not have to invent one. Every module used to answer the second question its own way
 * (`isPublished()` on a page, a date compared to now on an article, a flag on a rubric), and
 * that was fine while only the handler asked. The sitemap asks it too, of thousands of rows at
 * once, so the answer has to exist as a query as well — and one written next to the other, so
 * that the page that answers 404 and the map that leaves it out cannot drift apart.
 *
 * The language is passed for the entity that is visible in one language and not another. None
 * of the first ones is: a translation that is missing is already a missing row in the registry.
 */
interface Visible
{
    public function isVisible(?string $locale = null): bool;

    /**
     * The same answer as a query, for lists and the sitemap.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder;

    /** For `<lastmod>`: when what a reader sees last changed. */
    public function visibleUpdatedAt(): ?CarbonInterface;
}
