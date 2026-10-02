<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use WebxUi\Catalog\Storefront\HasDefaultSort;
use WebxUi\Catalog\Storefront\HasListingTexts;
use WebxUi\Catalog\Storefront\ListingSubject;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Rendering\SeoData;

/**
 * What a landing of `module-catalog-landings` will be to the core: the owner of a category's page
 * with a set chosen in advance — its own name, card, texts and order.
 */
final class Landing implements HasDefaultSort, HasListingTexts, ListingSubject
{
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly ?string $above = null,
        private readonly ?string $below = null,
        private readonly ?string $sort = null,
        private readonly ?string $title = null,
    ) {}

    public function displayName(string $locale): string
    {
        return $this->name;
    }

    public function seoData(?string $locale = null): SeoData
    {
        return SeoData::make(['title' => $this->title, 'h1' => $this->name]);
    }

    public function breadcrumbs(string $locale): array
    {
        return [new Crumb($this->name, $this->url)];
    }

    public function textAbove(string $locale): ?string
    {
        return $this->above;
    }

    public function textBelow(string $locale): ?string
    {
        return $this->below;
    }

    public function defaultSort(): ?string
    {
        return $this->sort;
    }
}
