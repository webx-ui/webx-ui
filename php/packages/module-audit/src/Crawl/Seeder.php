<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\SiteClient;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;

/**
 * Stage 3 of a full run (§3): the home page and the home of every other language the site puts
 * in its addresses, every address of the sitemap (through its index), and the canonical rows of
 * the `routing` registry. Where a page came from is remembered — an address in the sitemap or the
 * registry that no link leads to is an orphan, and one the crawl found that the sitemap never
 * named is a gap in the sitemap.
 */
final class Seeder
{
    public function __construct(
        private readonly Container $container,
        private readonly SitemapReader $sitemaps,
    ) {}

    public function seed(AuditRun $run, SiteClient $client, HostClassifier $hosts): RobotsRules
    {
        $base = rtrim($run->base_url, '/');
        $frontier = new Frontier($run);

        $robotsAnswer = $client->get($base.'/robots.txt');
        $robots = $robotsAnswer->ok() ? RobotsRules::parse($robotsAnswer->body) : new RobotsRules;

        // A recheck asks its addresses and nothing else; robots.txt is still read, for the
        // "closed in robots.txt" mark on each of them.
        if ($run->scope === AuditRun::URLS) {
            $frontier->add($run->urls(), AuditPage::LIST, null);

            return $robots;
        }

        $frontier->add([Urls::normalise($base.'/') ?? $base.'/'], AuditPage::HOME, 0);
        $frontier->add($this->languageHomes($base), AuditPage::HOME, 0);

        $this->sitemaps($base, $robots, $client, $hosts, $frontier);
        $this->registry($base, $frontier);

        return $robots;
    }

    /**
     * `/de`, `/fr` — the home of each language with a prefix (§12, decided). Every language
     * version is a page of its own under the same page limit, found from its own home even when
     * the sitemap and the registry say nothing about it; hreflang and links bring the rest.
     * Depth counts from that home, as it does for the main one.
     *
     * @return list<string>
     */
    private function languageHomes(string $base): array
    {
        if (! class_exists(SiteUrl::class)) {
            return [];
        }

        try {
            $locales = $this->container->make(Locales::class);
            $site = $this->container->make(SiteUrl::class);
            $urls = [];

            foreach ($locales->codes() as $code) {
                $prefix = $site->prefix($code);
                $url = $prefix === '' ? null : Urls::normalise($base.'/'.$prefix);

                if ($url !== null) {
                    $urls[] = $url;
                }
            }

            return array_values(array_unique($urls));
        } catch (Throwable) {
            return [];
        }
    }

    private function sitemaps(string $base, RobotsRules $robots, SiteClient $client, HostClassifier $hosts, Frontier $frontier): void
    {
        $own = $this->sitemaps->read($base, $robots->sitemaps, $client, $hosts)->urls;

        // Past the page limit nothing new is added, but what is already there still learns that
        // the sitemap lists it — `sitemap.missing_page` reads that flag.
        foreach (array_chunk($own, 500) as $chunk) {
            $frontier->add($chunk, AuditPage::SITEMAP, null, ['in_sitemap' => true]);
        }
    }

    /**
     * The canonical rows of the address registry, when `routing` is installed: every address the
     * site says it has, including the ones nothing links to.
     */
    private function registry(string $base, Frontier $frontier): void
    {
        if (! class_exists(Route::class) || $frontier->full()) {
            return;
        }

        try {
            if (! Schema::hasTable((new Route)->getTable())) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        try {
            $site = $this->container->make(SiteUrl::class);
        } catch (Throwable) {
            $site = null;
        }

        Route::query()->canonical()->orderBy('id')->chunk(500, function ($rows) use ($base, $site, $frontier): bool {
            $urls = [];

            foreach ($rows as $row) {
                /** @var Route $row */
                $path = UrlNormaliser::join($site?->prefix($row->locale) ?? '', $row->path);
                $url = Urls::normalise($base.'/'.$path);

                if ($url !== null) {
                    $urls[] = $url;
                }
            }

            $frontier->add($urls, AuditPage::REGISTRY, null, ['in_registry' => true]);

            return ! $frontier->full();
        });
    }
}
