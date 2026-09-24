<?php

declare(strict_types=1);

namespace WebxUi\Seo\Contracts;

/**
 * Schema.org blocks an entity says about itself — an article is a `BlogPosting`, a service a
 * `Service` — written in code by the module that owns it (§17.1, decision 7).
 *
 * Each block is printed as its own `<script type="application/ld+json">`, after the site's
 * `Organization` and the `BreadcrumbList`, so each one carries its own `@context`. What depends
 * on the page being answered rather than on the entity — the articles on page two of a rubric —
 * is not the entity's to say: the handler puts it in with `Seo::push()`.
 */
interface HasStructuredData
{
    /** @return list<array<string, mixed>> */
    public function structuredData(string $locale): array;
}
