<?php

declare(strict_types=1);

namespace WebxUi\Seo\Sitemap;

/**
 * Named routes that belong in the sitemap although no entity stands behind them.
 *
 * The feed of a blog, the index of services: ordinary routes a module registers itself, so the
 * address registry has no row for them — and the registry is where the sitemap takes every other
 * address from (§17.1, decision 4). A module names them from its provider:
 *
 *     $this->app->make(SitemapRoutes::class)->register('webx.blog.feed');
 *
 * They are checked like everything else, by the SEO resolver: a feed an editor closed with a
 * `noindex` rule drops out of the map without anybody telling this list.
 *
 * Only routes without parameters: one with them is a family of addresses, and nothing here
 * could know which members of it exist.
 */
final class SitemapRoutes
{
    /** @var list<string> */
    private array $names = [];

    public function register(string $name): void
    {
        if (! in_array($name, $this->names, true)) {
            $this->names[] = $name;
        }
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->names;
    }

    public function forget(): void
    {
        $this->names = [];
    }
}
