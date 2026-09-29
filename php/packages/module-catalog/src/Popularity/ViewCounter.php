<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Popularity;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use WebxUi\Catalog\Models\Product;

/**
 * Views of product pages, counted in the cache and written down in bulk (§9).
 *
 * A view is not a write to the database: a product page is the busiest page of a shop, and a
 * `update … set views = views + 1` on each would make the busiest page the slowest. The counts
 * wait in one cache entry under a lock, and `webx:catalog:flush-views` takes them every five
 * minutes. A view that cannot get the lock in a moment is dropped — popularity is an estimate,
 * and a page that waits for it is not.
 *
 * Crawlers are not readers. The list is the plain one on purpose: whoever wants to look like a
 * browser will, and a precise count was never the point.
 */
final class ViewCounter
{
    public const KEY = 'webx.catalog.views.pending';

    private const BOTS = '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|quora link|outbrain|pinterest|vkshare|w3c_validator|preview|headless|lighthouse|curl|wget|python-requests|httpclient/i';

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('webx-catalog.popularity.views', true);
    }

    public function record(Product $product, Request $request): void
    {
        if (! $this->enabled() || $this->isBot((string) $request->userAgent())) {
            return;
        }

        $id = (int) $product->id;

        $this->locked(function () use ($id): void {
            /** @var array<int, int> $pending */
            $pending = (array) $this->cache->get(self::KEY, []);
            $pending[$id] = ($pending[$id] ?? 0) + 1;
            $this->cache->forever(self::KEY, $pending);
        });
    }

    /**
     * Everything counted so far, and the count starts again from nothing.
     *
     * @return array<int, int> product id → views
     */
    public function take(): array
    {
        $taken = [];

        $this->locked(function () use (&$taken): void {
            /** @var array<int, int> $pending */
            $pending = (array) $this->cache->get(self::KEY, []);
            $this->cache->forget(self::KEY);
            $taken = $pending;
        }, wait: 10);

        return $taken;
    }

    public function isBot(string $agent): bool
    {
        return $agent === '' || preg_match(self::BOTS, $agent) === 1;
    }

    private function locked(callable $work, int $wait = 1): void
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            $work();

            return;
        }

        try {
            $store->lock(self::KEY.'.lock', 10)->block($wait, $work);
        } catch (LockTimeoutException) {
            // Busy: this view goes uncounted, and the page does not wait for it.
        }
    }
}
