<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Demo;

use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogFeeds\Http\FeedForm;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * A Google Merchant feed on the demo shop, saved through the form an editor uses, so it has passed
 * the same trial (§8.1 of the feeds spec).
 *
 * Nothing is built here. The demos of the stock, the labels and the properties run after this one,
 * and a file made now would say everything is in stock. A daily feed that was never built is due at
 * once, so the scheduler's next minute queues the first build, over a finished demo; on a site
 * without a scheduler the panel's button does it. A build, its file and its log go with the feed.
 */
final class FeedsDemo
{
    public const NAME = 'Demo Google Merchant';

    public function __construct(private readonly FeedForm $form) {}

    public function seed(DemoLedger $ledger): void
    {
        if ($ledger->idsOf('catalog', Product::class) === []) {
            $ledger->note('The catalogue demo is not seeded; a feed would have no products to go through.');

            return;
        }

        if (Feed::query()->exists()) {
            $ledger->note('The site already has feeds; the demo left them alone.');

            return;
        }

        // A feed speaks the site's currency and has no other; without one it cannot be saved.
        if (Feed::siteCurrency() === '') {
            $ledger->note('The site has no currency (WEBX_CATALOG_CURRENCY), so the demo made no feed.');

            return;
        }

        $saved = $this->form->save(new Feed, [
            'name' => self::NAME,
            'preset' => 'google',
            'schedule' => 'daily',
            'rebuild_on_change' => true,
        ]);

        $ledger->created($saved->feed, 'Feed '.self::NAME);
    }
}
