<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Audit\Http\Resources\IssueResource;
use WebxUi\Audit\Pages\PageColumns;
use WebxUi\Audit\Pages\PageQuery;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;

/**
 * The pages screen and the page's card (§8): every crawled address with any field of its
 * snapshot, filtered and sorted on any of them, as a table or as CSV — and one page with its
 * findings and its links in and out.
 */
final class PageController
{
    /** Rows of the CSV held in memory at once. */
    private const CHUNK = 500;

    public function index(Request $request, AuditRun $run): JsonResponse
    {
        $pages = PageQuery::build($run, $request)->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return response()->json([
            'data' => array_map(self::row(...), $pages->items()),
            'meta' => [
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
                'from' => $pages->firstItem(),
                'to' => $pages->lastItem(),
            ],
        ]);
    }

    /**
     * The list as a spreadsheet, under the same filters — every column the screen shows, or
     * every column there is when `columns` is not given. Streamed: a run is up to the page limit.
     */
    public function export(Request $request, AuditRun $run): StreamedResponse
    {
        $asked = array_values(array_filter(
            explode(',', $request->string('columns')->toString()),
            PageColumns::has(...),
        ));
        $columns = $asked === [] ? array_keys(PageColumns::ALL) : $asked;
        $query = PageQuery::build($run, $request);

        $name = 'audit-'.$run->id.'-pages-'.Carbon::now()->format('Y-m-d-His').'.csv';

        return new StreamedResponse(function () use ($query, $columns): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // Excel opens UTF-8 as the local code page unless told otherwise.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(static fn (string $key): string => (string) __('webx-audit::page.field-'.$key), $columns), escape: '');

            $query->chunk(self::CHUNK, function ($pages) use ($out, $columns): void {
                foreach ($pages as $page) {
                    fputcsv($out, array_map(static fn (string $key): string => self::cell(PageColumns::value($page, $key)), $columns), escape: '');
                }
            });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** One page: the whole snapshot, its findings, and how many links lead in and out. */
    public function show(AuditRun $run, AuditPage $page): JsonResponse
    {
        abort_unless($page->run_id === $run->id, 404);

        $issues = AuditIssue::query()->where('page_id', $page->id)->whereNull('ignored_by')->orderBy('id')->get();

        return ApiResponse::data([
            'page' => [
                ...self::row($page),
                'headers' => $page->headers ?? [],
                'h1' => $page->h1 ?? [],
                'headings' => $page->headings ?? [],
                'hreflang' => $page->hreflang ?? [],
                'og' => $page->og ?? [],
                'twitter' => $page->twitter ?? [],
                'json_ld' => $page->json_ld ?? [],
                'error' => $page->error,
                'facts' => $page->facts ?? [],
                'fetched_at' => $page->fetched_at?->toAtomString(),
            ],
            'issues' => IssueResource::collection($issues)->resolve(),
            'counts' => [
                'issues' => $issues->count(),
                'incoming' => AuditLink::query()->where('to_page_id', $page->id)->count(),
                'outgoing' => AuditLink::query()->where('from_page_id', $page->id)->count(),
            ],
        ]);
    }

    /**
     * The links of a page: `direction=out` (default) — what it points at, with the answer of
     * every own page; `in` — the pages that point at it.
     */
    public function links(Request $request, AuditRun $run, AuditPage $page): JsonResponse
    {
        abort_unless($page->run_id === $run->id, 404);

        $incoming = $request->string('direction')->toString() === 'in';

        $links = AuditLink::query()
            ->where($incoming ? 'to_page_id' : 'from_page_id', $page->id)
            ->with($incoming ? 'from:id,url,status' : 'to:id,url,status')
            ->when($request->filled('kind'), static fn ($query) => $query->where('kind', $request->string('kind')->toString()))
            ->orderBy('id')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return response()->json([
            'data' => array_map(static fn (AuditLink $link): array => [
                'id' => $link->id,
                'url' => $incoming ? $link->from?->url : $link->to_url,
                'page_id' => $incoming ? $link->from_page_id : $link->to_page_id,
                'status' => $incoming ? $link->from?->status : ($link->to_page_id === null ? $link->status : $link->to?->status),
                'kind' => $link->kind,
                'anchor' => $link->anchor,
                'rel' => $link->rel,
                'target' => $link->target,
                'host' => $link->host,
                'host_class' => $link->host_class,
                'absolute' => $link->absolute,
            ], $links->items()),
            'meta' => [
                'current_page' => $links->currentPage(),
                'last_page' => $links->lastPage(),
                'per_page' => $links->perPage(),
                'total' => $links->total(),
                'from' => $links->firstItem(),
                'to' => $links->lastItem(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(AuditPage $page): array
    {
        $row = ['id' => $page->id];

        foreach (array_keys(PageColumns::ALL) as $key) {
            $row[$key] = PageColumns::value($page, $key);
        }

        return $row;
    }

    private static function cell(mixed $value): string
    {
        $text = match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => '',
        };

        // A cell that starts like a formula is one in a spreadsheet — a title can start with "=".
        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }
}
