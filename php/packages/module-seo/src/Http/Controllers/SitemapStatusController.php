<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Seo\Sitemap\Sitemap;

/**
 * The card above the rules (§17.6): where the map is, what is in it, when it was built, and how
 * many visible addresses the resolver kept out of it — the first thing to look at when a page is
 * missing from a search engine.
 */
final class SitemapStatusController
{
    public function __construct(private readonly Sitemap $sitemap) {}

    public function show(): JsonResponse
    {
        return ApiResponse::data($this->sitemap->status());
    }

    /**
     * Build it again now. The map rebuilds itself on every save that could change it; this is
     * for the change nothing announces — a date that has come — and for somebody who wants to
     * see the numbers move.
     */
    public function rebuild(): JsonResponse
    {
        $this->sitemap->refresh();
        $this->sitemap->build();

        return ApiResponse::data($this->sitemap->status());
    }
}
