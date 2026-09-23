<?php

declare(strict_types=1);

namespace WebxUi\Seo\Contracts;

/**
 * An entity that knows where it stands on the site.
 *
 * The trail above it, itself last, the site's home left out: `module-seo` puts the home in front
 * of every trail itself, under the name the settings give it (§17.2). One list, printed twice —
 * as the `BreadcrumbList` in the `<head>` and as the crumbs a reader sees — so that the two
 * cannot tell different stories once the main rubric of an article changes (§17.1, decision 6).
 *
 *     public function breadcrumbs(string $locale): array
 *     {
 *         return [
 *             new Crumb($this->category->getTranslation('title', $locale), $this->category->url($locale)),
 *             new Crumb($this->getTranslation('title', $locale), $this->url($locale)),
 *         ];
 *     }
 *
 * An empty list means "no trail here" — the home page, or anything that is not a page of its own.
 */
interface HasBreadcrumbs
{
    /** @return list<Crumb> */
    public function breadcrumbs(string $locale): array;
}
