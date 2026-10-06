<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditRun;

/**
 * «Outgoing» (§8): every host the site points at, by class — stands first — with how many links
 * and pages the last full run saw, what they answered, how many fields of the database hold it,
 * and the run it was first seen in among the snapshots kept. `host` opens one: the pages with
 * their anchors and the records with the field and the way to the editor.
 */
final class HostController
{
    private const ORDER = [HostClassifier::DEV => 0, HostClassifier::OWN_MIRROR => 1, HostClassifier::EXTERNAL => 2, HostClassifier::OWN => 3];

    private const LIMIT = 200;

    public function index(Request $request): JsonResponse
    {
        $class = $request->filled('class') ? $request->string('class')->toString() : null;
        $search = $request->filled('search') ? strtolower($request->string('search')->toString()) : null;
        $crawled = AuditRun::query()->where('status', AuditRun::DONE)->where('scope', AuditRun::FULL)->orderByDesc('id')->first();
        $done = AuditRun::query()->siteWide()->where('status', AuditRun::DONE)->orderByDesc('id')->first();
        $hosts = [];

        if ($crawled !== null) {
            $links = DB::table('audit_links')->where('run_id', $crawled->id)->whereNotNull('host')
                ->when($class !== null, static fn ($query) => $query->where('host_class', $class))
                ->when($search !== null, static fn ($query) => $query->where('host', 'like', '%'.$search.'%'))
                ->groupBy('host', 'host_class')
                ->selectRaw("host, host_class, count(*) as links, count(distinct from_page_id) as pages, sum(case when status >= 400 or status = 0 then 1 else 0 end) as broken, sum(case when rel like '%nofollow%' then 1 else 0 end) as nofollow")
                ->get();

            foreach ($links as $row) {
                $hosts[(string) $row->host] = self::row((string) $row->host, (string) $row->host_class, [
                    'links' => (int) $row->links,
                    'pages' => (int) $row->pages,
                    'broken' => (int) $row->broken,
                    'nofollow' => (int) $row->nofollow,
                ]);
            }
        }

        if ($done !== null) {
            $fields = DB::table('audit_content_urls')->where('run_id', $done->id)
                ->when($class !== null, static fn ($query) => $query->where('host_class', $class))
                ->when($search !== null, static fn ($query) => $query->where('host', 'like', '%'.$search.'%'))
                ->groupBy('host', 'host_class')
                ->selectRaw('host, host_class, count(*) as fields')
                ->get();

            foreach ($fields as $row) {
                $hosts[(string) $row->host] ??= self::row((string) $row->host, (string) $row->host_class, []);
                $hosts[(string) $row->host]['fields'] = (int) $row->fields;
            }
        }

        $hosts = array_values($hosts);
        usort($hosts, static fn (array $a, array $b): int => [self::ORDER[$a['class']] ?? 9, -$a['links'] - $a['fields'], $a['host']] <=> [self::ORDER[$b['class']] ?? 9, -$b['links'] - $b['fields'], $b['host']]);
        $hosts = self::firstSeen(array_slice($hosts, 0, self::LIMIT));

        $classes = [];

        foreach ($hosts as $host) {
            $classes[$host['class']] = ($classes[$host['class']] ?? 0) + 1;
        }

        $answer = [
            'crawled_run' => $crawled?->id,
            'database_run' => $done?->id,
            'classes' => $classes,
            'hosts' => $hosts,
        ];

        if ($request->filled('host')) {
            $answer = [...$answer, ...$this->places(strtolower($request->string('host')->toString()), $crawled, $done)];
        }

        return ApiResponse::data($answer);
    }

    /**
     * Where a host stands: the pages that link to it, and the fields of the database that hold it.
     *
     * `targets` is the same links by where they lead: a font or a profile link sits in the layout,
     * and by page it is one line forty times over. Each target counts all its pages and lists fifty.
     *
     * @return array{pages: list<array<string, mixed>>, targets: list<array<string, mixed>>, fields: list<array<string, mixed>>}
     */
    public function places(string $host, ?AuditRun $crawled, ?AuditRun $done): array
    {
        return [
            'pages' => $crawled === null ? [] : DB::table('audit_links')
                ->join('audit_pages', 'audit_pages.id', '=', 'audit_links.from_page_id')
                ->where('audit_links.run_id', $crawled->id)->where('audit_links.host', $host)
                // Broken first: the row's count says how many, and the fifty shown are the ones to
                // fix — otherwise they sit behind the menu of every page and never come into view.
                ->orderByRaw('case when audit_links.status >= 400 or audit_links.status = 0 then 0 else 1 end')
                ->orderBy('audit_links.id')
                ->limit(50)->get(['audit_pages.id as page_id', 'audit_pages.url as page', 'audit_links.to_url as url', 'audit_links.kind', 'audit_links.anchor', 'audit_links.rel', 'audit_links.status'])
                ->map(static fn (object $row): array => (array) $row)->all(),
            'targets' => $crawled === null ? [] : $this->targets($host, $crawled),
            'fields' => $done === null ? [] : DB::table('audit_content_urls')
                ->where('run_id', $done->id)->where('host', $host)
                ->orderBy('id')
                ->limit(50)->get(['source', 'record_id', 'record_label', 'field', 'locale', 'url', 'published', 'edit_url'])
                ->map(static fn (object $row): array => (array) $row)->all(),
        ];
    }

    /**
     * The addresses on a host the pages point at, broken first, then by how many pages have them.
     *
     * @return list<array{url: string, kind: string, status: int|null, pages: int, places: list<array{page: string, anchor: string|null}>}>
     */
    private function targets(string $host, AuditRun $crawled): array
    {
        $links = static fn () => DB::table('audit_links')->where('audit_links.run_id', $crawled->id)->where('audit_links.host', $host);

        return $links()
            ->groupBy('to_url', 'kind')
            ->orderByRaw('min(case when status >= 400 or status = 0 then 0 else 1 end)')
            ->orderByRaw('count(distinct from_page_id) desc')
            ->orderBy('to_url')
            ->limit(50)
            ->get(['to_url', 'kind', DB::raw('max(status) as status'), DB::raw('count(distinct from_page_id) as pages')])
            ->map(static fn (object $target): array => [
                'url' => (string) $target->to_url,
                'kind' => (string) $target->kind,
                'status' => $target->status === null ? null : (int) $target->status,
                'pages' => (int) $target->pages,
                'places' => $links()
                    ->join('audit_pages', 'audit_pages.id', '=', 'audit_links.from_page_id')
                    ->where('audit_links.to_url', $target->to_url)->where('audit_links.kind', $target->kind)
                    ->groupBy('audit_pages.id', 'audit_pages.url')
                    ->orderByRaw('min(audit_links.id)')
                    ->limit(50)
                    ->get(['audit_pages.url as page', DB::raw('min(audit_links.anchor) as anchor')])
                    ->map(static fn (object $place): array => ['page' => (string) $place->page, 'anchor' => $place->anchor === null ? null : (string) $place->anchor])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{host: string, class: string, links: int, pages: int, broken: int, nofollow: int, fields: int, first_seen: string|null}
     */
    private static function row(string $host, string $class, array $counts): array
    {
        return [
            'host' => $host,
            'class' => $class,
            'links' => $counts['links'] ?? 0,
            'pages' => $counts['pages'] ?? 0,
            'broken' => $counts['broken'] ?? 0,
            'nofollow' => $counts['nofollow'] ?? 0,
            'fields' => 0,
            'first_seen' => null,
        ];
    }

    /**
     * When each host first turned up among the snapshots still kept — a domain that appeared
     * yesterday is a typo or somebody else's spam (`hosts.new_domain`).
     *
     * @param  list<array{host: string, class: string, links: int, pages: int, broken: int, nofollow: int, fields: int, first_seen: string|null}>  $hosts
     * @return list<array{host: string, class: string, links: int, pages: int, broken: int, nofollow: int, fields: int, first_seen: string|null}>
     */
    private static function firstSeen(array $hosts): array
    {
        if ($hosts === []) {
            return [];
        }

        $first = DB::table('audit_links')
            ->join('audit_runs', 'audit_runs.id', '=', 'audit_links.run_id')
            ->whereIn('audit_links.host', array_column($hosts, 'host'))
            ->groupBy('audit_links.host')
            ->selectRaw('audit_links.host as host, min(audit_runs.created_at) as seen')
            ->pluck('seen', 'host');

        foreach ($hosts as $index => $host) {
            $seen = $first[$host['host']] ?? null;
            $hosts[$index]['first_seen'] = is_string($seen) ? Carbon::parse($seen)->toAtomString() : null;
        }

        return $hosts;
    }
}
