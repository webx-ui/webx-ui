<?php

declare(strict_types=1);

namespace WebxUi\Seo\Console;

use Illuminate\Console\Command;
use WebxUi\Seo\Sitemap\Sitemap;

/**
 * Build the whole sitemap now instead of on the first request for it.
 *
 * For a deploy, so that the first crawler after it is not the one that waits, and for a site
 * large enough that building on a request is a request that times out. The map is correct
 * without it — every save that changes the answer already throws the built one away.
 */
final class SitemapCommand extends Command
{
    /** @var string */
    protected $signature = 'webx:seo:sitemap';

    /** @var string */
    protected $description = 'Build the sitemap and put it in the cache';

    public function handle(Sitemap $sitemap): int
    {
        if (! $sitemap->enabled()) {
            $this->components->warn('The sitemap is turned off (webx-seo.sitemap.enabled).');

            return self::SUCCESS;
        }

        $sitemap->refresh();
        $built = $sitemap->build();

        if ($built['counts'] === []) {
            $this->components->info('The sitemap is empty: no address is visible and open to the index.');

            return self::SUCCESS;
        }

        foreach ($built['counts'] as $file => $count) {
            $this->components->twoColumnDetail("sitemap-{$file}.xml", (string) $count);
        }

        $this->components->info(sprintf('%d addresses in %d files: %s', array_sum($built['counts']), count($built['counts']), $sitemap->url()));

        return self::SUCCESS;
    }
}
