<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\QueueBacklog;
use WebxUi\Admin\Gate\Credentials;
use WebxUi\Admin\Gate\Openings;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\Settings\Settings;

/**
 * Whether a marketplace gets what the panel says it gets (§7.6 and §13 of the feeds spec).
 *
 * A feed goes wrong quietly: the old file keeps being served and the address keeps answering, and
 * the first to notice is the marketplace. The key is never printed here, only the feed's name.
 *
 * What an editor mends in the panel is `content`; a queue nobody works through stops a deploy. A
 * closed site is a note: it is closed on purpose, and that is just why the marketplace sees nothing.
 */
final class FeedsCheck implements Check
{
    private const SUBJECT = 'Feeds';

    /** Share of `max_bytes` past which a feed is said to be near it. */
    private const NEAR_LIMIT = 0.9;

    private const CRAWLERS = ['Googlebot', 'AdsBot-Google'];

    public function __construct(
        private readonly QueueBacklog $queue,
        private readonly Credentials $credentials,
        private readonly Openings $openings,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        if (! Schema::hasTable('catalog_feeds')) {
            return [];
        }

        /** @var Collection<int, Feed> $feeds */
        $feeds = Feed::query()
            ->with(['lastBuild:id,feed_id,status', 'currentBuild:id,bytes,finished_at'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'schedule', 'current_build_id']);

        if ($feeds->isEmpty()) {
            return [];
        }

        $found = [];

        foreach ($feeds as $feed) {
            array_push($found, ...$this->feed($feed));
        }

        return [...$found, ...$this->queue(), ...$this->gate(), ...$this->robots()];
    }

    /** @return list<Diagnosis> */
    private function feed(Feed $feed): array
    {
        $found = [];
        $current = $feed->currentBuild;

        if ($feed->lastBuild?->status === FeedBuild::FAILED) {
            $found[] = Diagnosis::content(self::SUBJECT, "the last build of «{$feed->name}» failed, and the file before it is still served — see the journal of the feed.");
        }

        $period = Feed::PERIODS[$feed->schedule] ?? null;

        if ($period !== null && $current?->finished_at?->copy()->addMinutes(2 * $period)->lt(Carbon::now())) {
            $found[] = Diagnosis::content(self::SUBJECT, "the file of «{$feed->name}» is older than two periods of its schedule — a build is not getting through.");
        }

        if ((int) $current?->bytes >= self::NEAR_LIMIT * (int) $this->config->get('webx-catalog-feeds.max_bytes')) {
            $found[] = Diagnosis::content(self::SUBJECT, "the file of «{$feed->name}» is close to the limit of `max_bytes` — narrow what it selects or split it in two.");
        }

        return $found;
    }

    /** @return list<Diagnosis> */
    private function queue(): array
    {
        return $this->queue->stalled() > 0
            ? [Diagnosis::warn(self::SUBJECT, 'jobs wait on the queue and no worker takes them, so no feed gets built — '.QueueBacklog::advice().'.')]
            : [];
    }

    /** @return list<Diagnosis> */
    private function gate(): array
    {
        if (! $this->credentials->enabled() || $this->openings->open(Request::create('/'.$this->prefix().'/key.xml'))) {
            return [];
        }

        return [Diagnosis::note(self::SUBJECT, 'the site is closed by its password, and a marketplace knows no password — open `'.$this->prefix().'/*` with `webx-admin.gate.except` if it should read the feeds; product pages and images stay closed to it all the same.')];
    }

    /** @return list<Diagnosis> */
    private function robots(): array
    {
        $body = $this->robotsTxt();

        if ($body === null) {
            return [];
        }

        $paths = array_filter(['/'.$this->prefix().'/key.xml', $this->imagePath()]);
        $found = [];

        foreach (self::CRAWLERS as $crawler) {
            foreach ($paths as $path) {
                if (! RobotsRules::allows($body, $crawler, $path)) {
                    $found[] = Diagnosis::content(self::SUBJECT, "robots.txt closes {$path} to {$crawler}, and a feed needs it open — allow the feeds and the images.");
                }
            }
        }

        return $found;
    }

    /** What the web server answers first, then the setting, as a request would find them. */
    private function robotsTxt(): ?string
    {
        if ($this->config->get('webx-admin.public', true) === false) {
            return "User-agent: *\nDisallow: /";
        }

        $file = public_path('robots.txt');

        if (is_file($file)) {
            return (string) file_get_contents($file);
        }

        if (! $this->container->bound(Settings::class)) {
            return null;
        }

        $value = $this->container->make(Settings::class)->get('seo.robots-txt');

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** Where the images are on this site; null when they are on another host, with its own robots. */
    private function imagePath(): ?string
    {
        try {
            $address = Storage::disk((string) $this->config->get('webx-media.disk', 'public'))->url('feed.jpg');
        } catch (Throwable) {
            return null;
        }

        $host = parse_url($address, PHP_URL_HOST);

        if (is_string($host) && $host !== parse_url((string) $this->config->get('app.url'), PHP_URL_HOST)) {
            return null;
        }

        $path = parse_url($address, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }

    private function prefix(): string
    {
        return trim((string) $this->config->get('webx-catalog-feeds.path'), '/');
    }
}
