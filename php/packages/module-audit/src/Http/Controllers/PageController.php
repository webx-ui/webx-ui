<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
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
use WebxUi\Audit\Runs\AuditResource;
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

    /**
     * One page as a file (§8, «Export» on the card): the whole snapshot, its findings with their
     * details, and its links out with what each answered — what someone fixing the page by hand
     * wants on their desk, in a form a script can read too.
     */
    public function exportOne(AuditRun $run, AuditPage $page): JsonResponse
    {
        $card = $this->show($run, $page)->getData(true);
        $links = AuditLink::query()->where('from_page_id', $page->id)->with('resource')->orderBy('id')->limit(5000)->get();

        $body = [
            'run' => ['id' => $run->id, 'scope' => $run->scope, 'base_url' => $run->base_url, 'finished_at' => $run->finished_at?->toAtomString()],
            ...(is_array($card['data'] ?? null) ? $card['data'] : []),
            'links' => $links->map(static fn (AuditLink $link): array => [
                'url' => $link->to_url,
                'kind' => $link->kind,
                'anchor' => $link->anchor,
                'rel' => $link->rel,
                'host_class' => $link->host_class,
                'status' => $link->resource->status ?? $link->status,
            ])->all(),
        ];

        $name = 'audit-'.$run->id.'-page-'.$page->id.'.json';

        return response()->json($body, 200, [
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
                'images' => self::ofTab($page, 'images')->count(),
                'css' => self::ofTab($page, 'css')->count(),
                'js' => self::ofTab($page, 'js')->count(),
                'microdata' => count($page->json_ld ?? []),
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
     * What a page loads, a tab of the card at a time: `tab=images` (pictures, the Open Graph
     * picture and the icon), `css` or `js` — each with what it answered when stage 5 asked.
     */
    public function resources(Request $request, AuditRun $run, AuditPage $page): JsonResponse
    {
        abort_unless($page->run_id === $run->id, 404);

        $tab = $request->string('tab')->toString();
        abort_unless(in_array($tab, ['images', 'css', 'js'], true), 422);

        $links = self::ofTab($page, $tab)
            ->with('resource')
            ->orderBy('id')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return response()->json([
            'data' => array_map(static fn (AuditLink $link): array => [
                'id' => $link->id,
                'url' => $link->to_url,
                'kind' => $link->kind,
                'alt' => $link->anchor,
                'host_class' => $link->host_class,
                'checked' => $link->resource?->checked_at !== null,
                'status' => $link->resource?->status,
                'error' => $link->resource?->error,
                'location' => $link->resource?->location,
                'content_type' => $link->resource?->content_type,
                'bytes' => $link->resource?->bytes,
                'cache_control' => $link->resource?->cache_control,
                'compression' => $link->resource?->compression,
                'width' => $link->resource?->width,
                'height' => $link->resource?->height,
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
     * The links of a page that belong to a tab. A picture is one by its tag or by what stage 5
     * made of it (an icon, a `url()` in a style); a stylesheet the same way.
     *
     * @return Builder<AuditLink>
     */
    private static function ofTab(AuditPage $page, string $tab): Builder
    {
        $query = AuditLink::query()->where('from_page_id', $page->id);

        return match ($tab) {
            'images' => $query->where(static fn (Builder $images) => $images
                ->whereIn('kind', [AuditLink::IMG, AuditLink::SRCSET])
                ->orWhereHas('resource', static fn (Builder $resource) => $resource->whereIn('kind', AuditResource::PICTURES))),
            'css' => $query->where(static fn (Builder $css) => $css
                ->where(static fn (Builder $link) => $link->where('kind', AuditLink::LINK)->where('rel', 'like', '%stylesheet%'))
                ->orWhereHas('resource', static fn (Builder $resource) => $resource->where('kind', AuditResource::CSS))),
            default => $query->where(static fn (Builder $js) => $js
                ->where('kind', AuditLink::SCRIPT)
                ->orWhereHas('resource', static fn (Builder $resource) => $resource->where('kind', AuditResource::JS))),
        };
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
