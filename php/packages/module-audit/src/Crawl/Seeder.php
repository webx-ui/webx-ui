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
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;

/**
 * Stage 3 of a full run (§3): the home page, every address of the sitemap (through its index),
 * and the canonical rows of the `routing` registry. Where a page came from is remembered — an
 * address in the sitemap or the registry that no link leads to is an orphan, and one the crawl
 * found that the sitemap never named is a gap in the sitemap.
 */
final class Seeder
{
    /** Sitemap files read at most — an index of indexes is followed, not explored forever. */
    private const SITEMAP_FILES = 50;

    public function __construct(private readonly Container $container) {}

    public function seed(AuditRun $run, SiteClient $client, HostClassifier $hosts): RobotsRules
    {
        $base = rtrim($run->base_url, '/');
        $frontier = new Frontier($run);

        $frontier->add([Urls::normalise($base.'/') ?? $base.'/'], AuditPage::HOME, 0);

        $robotsAnswer = $client->get($base.'/robots.txt');
        $robots = $robotsAnswer->ok() ? RobotsRules::parse($robotsAnswer->body) : new RobotsRules;

        $this->sitemaps($base, $robots, $client, $hosts, $frontier);
        $this->registry($base, $frontier);

        return $robots;
    }

    private function sitemaps(string $base, RobotsRules $robots, SiteClient $client, HostClassifier $hosts, Frontier $frontier): void
    {
        $queue = [];

        foreach ([...$robots->sitemaps, $base.'/sitemap.xml'] as $address) {
            $url = Urls::normalise($address);

            if ($url !== null && $hosts->classify($url) === HostClassifier::OWN) {
                $queue[$url] = true;
            }
        }

        $read = [];

        while ($queue !== [] && count($read) < self::SITEMAP_FILES && ! $frontier->full()) {
            $file = (string) array_key_first($queue);
            unset($queue[$file]);
            $read[$file] = true;

            $answer = $client->get($file);

            if (! $answer->ok()) {
                continue;
            }

            $xml = str_starts_with($answer->body, "\x1f\x8b") ? (string) @gzdecode($answer->body) : $answer->body;
            $locations = self::locations($xml);

            if (str_contains($xml, '<sitemapindex')) {
                foreach ($locations as $location) {
                    if (! isset($read[$location]) && $hosts->classify($location) === HostClassifier::OWN) {
                        $queue[$location] = true;
                    }
                }

                continue;
            }

            $own = array_values(array_filter($locations, static fn (string $url): bool => $hosts->classify($url) === HostClassifier::OWN));

            foreach (array_chunk($own, 500) as $chunk) {
                $frontier->add($chunk, AuditPage::SITEMAP, null, ['in_sitemap' => true]);
            }
        }
    }

    /**
     * Every `<loc>`, normalised.
     *
     * @return list<string>
     */
    private static function locations(string $xml): array
    {
        if (preg_match_all('~<loc>\s*(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?\s*</loc>~is', $xml, $matches) === 0) {
            return [];
        }

        $found = [];

        foreach ($matches[1] as $value) {
            $url = Urls::normalise(html_entity_decode(trim($value), ENT_QUOTES | ENT_XML1));

            if ($url !== null) {
                $found[$url] = true;
            }
        }

        return array_keys($found);
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
