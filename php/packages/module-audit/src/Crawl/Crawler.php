<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\ProbeResponse;
use WebxUi\Audit\Probes\SiteClient;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;

/**
 * Stage 4 (§3): breadth first from the seed, the site's own host only, a few requests at a time,
 * for as long as the piece has. Redirects are not followed — each step is a page of its own, and
 * the address it leads to joins the queue like a link would.
 *
 * Then, once nothing is left, the snapshot is finished in one go: incoming links, where each
 * chain of redirects ends, and which pages are indexable.
 */
final class Crawler
{
    /** Asked with `HEAD`: the crawl wants the answer, not a download of the file. */
    private const FILES = '~\.(pdf|zip|rar|7z|gz|tar|docx?|xlsx?|pptx?|odt|ods|csv|mp3|mp4|webm|mov|avi|jpe?g|png|gif|webp|avif|svg|ico|woff2?|ttf|eot|css|js|json|xml)$~i';

    /** The headers kept in the snapshot — the ones a check or a person reads. */
    private const HEADERS = [
        'content-type', 'content-length', 'content-encoding', 'x-encoded-content-encoding', 'cache-control', 'expires',
        'etag', 'last-modified', 'location', 'link', 'x-robots-tag', 'content-language', 'vary', 'server',
        'x-powered-by', 'strict-transport-security', 'content-security-policy', 'x-frame-options',
        'x-content-type-options', 'referrer-policy',
    ];

    public function __construct(
        private readonly PageParser $parser,
        private readonly Config $config,
    ) {}

    /**
     * Asks the queue, a few at a time, until the deadline; true once nothing is left.
     */
    public function fetch(AuditRun $run, SiteClient $client, HostClassifier $hosts, RobotsRules $robots, float $deadline): bool
    {
        $frontier = new Frontier($run);
        $concurrency = max(1, (int) $this->config->get('webx-audit.concurrency', 2));

        do {
            /** @var list<AuditPage> $pages */
            $pages = AuditPage::query()
                ->where('run_id', $run->id)
                ->whereNull('fetched_at')
                ->orderByRaw('depth is null')
                ->orderBy('depth')
                ->orderBy('id')
                ->limit($concurrency)
                ->get()
                ->all();

            if ($pages === []) {
                return true;
            }

            $requests = [];

            foreach ($pages as $page) {
                $requests[(string) $page->id] = [preg_match(self::FILES, (string) parse_url($page->url, PHP_URL_PATH)) === 1 ? 'HEAD' : 'GET', $page->url];
            }

            $answers = $client->many($requests);

            foreach ($pages as $page) {
                $answer = $answers[(string) $page->id] ?? new ProbeResponse($page->url, null, error: 'No answer.');
                $this->store($run, $page, $answer, $requests[(string) $page->id][0], $hosts, $robots, $frontier);
            }

            $run->increment('pages_crawled', count($pages));
        } while (microtime(true) < $deadline);

        return false;
    }

    /** Incoming links, where every chain ends, and what is indexable — once, after the crawl. */
    public function finish(AuditRun $run): void
    {
        // Raw SQL does not get the connection's table prefix by itself.
        $connection = AuditPage::query()->getConnection();
        $prefix = $connection instanceof Connection ? $connection->getTablePrefix() : '';
        $links = $prefix.(new AuditLink)->getTable();
        $pages = $prefix.(new AuditPage)->getTable();

        AuditPage::query()->where('run_id', $run->id)->update([
            'links_in' => DB::raw("(select count(distinct l.from_page_id) from {$links} l where l.to_page_id = {$pages}.id and l.kind = 'a' and l.from_page_id <> {$pages}.id)"),
        ]);

        /** @var array<string, array{id: int, status: int|null, redirect_to: string|null}> $byUrl */
        $byUrl = [];

        foreach (AuditPage::query()->where('run_id', $run->id)->get(['id', 'url', 'status', 'redirect_to']) as $page) {
            $byUrl[$page->url] = ['id' => $page->id, 'status' => $page->status, 'redirect_to' => $page->redirect_to];
        }

        foreach ($byUrl as $page) {
            $final = $page['status'];
            $next = $page['redirect_to'];
            $seen = [];

            while ($next !== null && isset($byUrl[$next]) && ! isset($seen[$next]) && count($seen) < 10) {
                $seen[$next] = true;
                $final = $byUrl[$next]['status'];
                $next = $byUrl[$next]['redirect_to'];
            }

            // A chain that leaves the snapshot or loops ends nowhere we know of.
            if ($page['redirect_to'] !== null && ($next !== null)) {
                $final = null;
            }

            AuditPage::query()->whereKey($page['id'])->update(['final_status' => $final]);
        }

        AuditPage::query()->where('run_id', $run->id)->whereNotNull('fetched_at')->lazyById(200)->each(function (AuditPage $page): void {
            $indexable = $page->html() && ! $page->noindex() && ($page->canonical === null || Urls::normalise($page->canonical) === $page->url);

            if ($indexable !== $page->indexable) {
                $page->update(['indexable' => $indexable]);
            }
        });
    }

    private function store(AuditRun $run, AuditPage $page, ProbeResponse $answer, string $method, HostClassifier $hosts, RobotsRules $robots, Frontier $frontier): void
    {
        $contentType = $answer->header('content-type');
        $headers = array_intersect_key($answer->headers, array_flip(self::HEADERS));

        $columns = [
            'fetched_at' => Carbon::now(),
            'status' => $answer->status,
            'error' => $answer->error === null ? null : mb_substr($answer->error, 0, 255),
            'content_type' => $contentType === null ? null : mb_substr($contentType, 0, 128),
            'bytes' => $method === 'HEAD' ? (is_numeric($answer->header('content-length')) ? (int) $answer->header('content-length') : null) : strlen($answer->body),
            'ttfb_ms' => $answer->ttfb,
            'total_ms' => $answer->ms,
            'compression' => self::compression($answer),
            'headers' => array_map(static fn (string $value): string => mb_substr($value, 0, 500), $headers),
            'x_robots_tag' => $answer->header('x-robots-tag') === null ? null : mb_substr((string) $answer->header('x-robots-tag'), 0, 255),
            'blocked_by_robots' => $robots->blocks(Urls::pathOf($page->url)),
        ];

        if ($answer->redirect()) {
            $location = Urls::normalise((string) $answer->location());
            $columns['redirect_to'] = $location === null ? null : mb_substr($location, 0, 2048);

            if ($location !== null && $hosts->classify($location) === HostClassifier::OWN) {
                $frontier->add([$location], AuditPage::LINK, $page->depth);
            }
        }

        if ($method === 'GET' && $answer->status === 200 && AuditPage::isHtml($contentType)) {
            $parsed = $this->parser->parse($answer->body, $page->url, self::charset($contentType));
            $columns = [...$columns, ...$parsed->columns, 'facts' => [...$parsed->facts, 'canonical_header' => self::canonicalHeader($answer, $page->url)]];
            $columns = [...$columns, ...$this->links($run, $page, $parsed->links, $hosts, $frontier)];
        }

        $page->update($columns);
    }

    /**
     * Stores the page's addresses, puts its own pages in the queue, and counts its links.
     *
     * @param  list<FoundLink>  $found
     * @return array{links_out_internal: int, links_out_external: int}
     */
    private function links(AuditRun $run, AuditPage $page, array $found, HostClassifier $hosts, Frontier $frontier): array
    {
        $classified = [];
        $crawl = [];
        $own = [];

        foreach ($found as $link) {
            $host = HostClassifier::hostOf($link->url);
            $class = $host === null ? null : $hosts->classifyHost($host);
            $classified[] = [$link, $host, $class];

            if ($class !== HostClassifier::OWN) {
                continue;
            }

            $own[AuditPage::hash($link->url)] = true;

            // Pages: what a visitor clicks, and what the head says is the page elsewhere.
            if ($link->kind === AuditLink::A || ($link->kind === AuditLink::LINK && in_array($link->rel, ['canonical', 'alternate'], true))) {
                $crawl[$link->kind === AuditLink::A ? 'a' : 'head'][] = $link->url;
            }
        }

        if (isset($crawl['a'])) {
            $frontier->add(array_values(array_unique($crawl['a'])), AuditPage::LINK, $page->depth === null ? null : $page->depth + 1);
        }

        if (isset($crawl['head'])) {
            $frontier->add(array_values(array_unique($crawl['head'])), AuditPage::LINK, null);
        }

        $ids = $own === [] ? [] : $frontier->ids(array_keys($own));
        $now = Carbon::now();
        $rows = [];
        $internal = 0;
        $external = 0;

        foreach ($classified as [$link, $host, $class]) {
            if ($link->kind === AuditLink::A) {
                $class === HostClassifier::OWN ? $internal++ : $external++;
            }

            $rows[] = [
                'run_id' => $run->id,
                'from_page_id' => $page->id,
                'to_url' => mb_substr($link->url, 0, 2048),
                'to_page_id' => $class === HostClassifier::OWN ? ($ids[AuditPage::hash($link->url)] ?? null) : null,
                'kind' => $link->kind,
                'anchor' => $link->anchor,
                'rel' => $link->rel === null ? null : mb_substr($link->rel, 0, 64),
                'target' => $link->target,
                'host' => $host === null ? null : mb_substr($host, 0, 255),
                'host_class' => $class,
                'absolute' => $link->absolute,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // A retried piece stores the page again; its old links go first.
        AuditLink::query()->where('from_page_id', $page->id)->delete();

        foreach (array_chunk($rows, 200) as $chunk) {
            AuditLink::query()->insert($chunk);
        }

        return ['links_out_internal' => $internal, 'links_out_external' => $external];
    }

    /** Guzzle decodes the body and moves the original header aside; either one says. */
    private static function compression(ProbeResponse $answer): ?string
    {
        $encoding = $answer->header('x-encoded-content-encoding') ?? $answer->header('content-encoding');

        return $encoding === null || trim($encoding) === '' ? null : mb_substr(strtolower(trim($encoding)), 0, 16);
    }

    private static function charset(?string $contentType): ?string
    {
        return $contentType !== null && preg_match('~charset=["\']?([\w-]+)~i', $contentType, $match) === 1 ? $match[1] : null;
    }

    /** The canonical of the `Link` header, absolute — `canonical.multiple` compares it with the tag. */
    private static function canonicalHeader(ProbeResponse $answer, string $url): ?string
    {
        $header = $answer->header('link');

        if ($header === null || preg_match('~<([^>]+)>\s*;[^,]*rel=["\']?canonical~i', $header, $match) !== 1) {
            return null;
        }

        return Urls::resolve($url, $match[1]);
    }
}
