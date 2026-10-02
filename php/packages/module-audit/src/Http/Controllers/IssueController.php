<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\CheckTexts;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Fixes\AuditFixes;
use WebxUi\Audit\Http\Resources\IssueResource;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditRun;

/**
 * The findings screen (§8): the checks that found something, then the addresses of one.
 */
final class IssueController
{
    public function __construct(
        private readonly AuditChecks $checks,
        private readonly AuditFixes $fixes,
    ) {}

    /**
     * One row per check with findings, worst first, with its three texts and its counts.
     * Filters: `severity`, `group`, `state` (`new`, `persisting`).
     */
    public function checks(Request $request, AuditRun $run): JsonResponse
    {
        $rows = $this->filtered($request, $run)
            // `check` is a keyword on MySQL; `select()` quotes it in the connection's grammar.
            ->select(['check', 'severity', 'state'])
            ->selectRaw('count(*) as total')
            ->groupBy('check', 'severity', 'state')
            ->get();

        $byCheck = [];

        foreach ($rows as $row) {
            $id = (string) $row->getAttribute('check');
            $byCheck[$id] ??= ['count' => 0, 'new' => 0, 'severity' => Severity::NOTICE];
            $total = (int) $row->getAttribute('total');
            $byCheck[$id]['count'] += $total;
            $byCheck[$id]['new'] += $row->getAttribute('state') === AuditIssue::NEW ? $total : 0;

            $severity = (string) $row->getAttribute('severity');

            if (Severity::weight($severity) > Severity::weight($byCheck[$id]['severity'])) {
                $byCheck[$id]['severity'] = $severity;
            }
        }

        $group = $request->string('group')->toString();
        $data = [];

        foreach ($byCheck as $id => $counts) {
            $check = $this->checks->get($id);

            if ($group !== '' && $check?->group() !== $group) {
                continue;
            }

            $data[] = [
                'id' => $id,
                'group' => $check?->group() ?? 'other',
                // The fixes that can close this check: the screen asks for a finding's offers
                // only when there are any.
                'fixes' => $this->fixes->forCheck($id),
                ...$counts,
                ...($check === null ? ['title' => $id, 'found' => '', 'why' => '', 'fix' => ''] : CheckTexts::of($check)),
            ];
        }

        usort($data, static fn (array $a, array $b): int => [Severity::weight($b['severity']), $b['count']] <=> [Severity::weight($a['severity']), $a['count']]);

        return ApiResponse::data($data);
    }

    /** The addresses of one check, or of all of them, paginated. */
    public function index(Request $request, AuditRun $run): AnonymousResourceCollection
    {
        $query = $this->filtered($request, $run)->orderBy('id');

        if ($request->filled('check')) {
            $query->where('check', $request->string('check')->toString());
        }

        return IssueResource::collection($query->paginate(min(200, max(1, $request->integer('per_page', 50)))));
    }

    /**
     * @return Builder<AuditIssue>
     */
    private function filtered(Request $request, AuditRun $run): Builder
    {
        $query = AuditIssue::query()->where('run_id', $run->id)->whereNull('ignored_by');

        if ($request->filled('severity') && Severity::valid($request->string('severity')->toString())) {
            $query->where('severity', $request->string('severity')->toString());
        }

        if (in_array($request->string('state')->toString(), [AuditIssue::NEW, AuditIssue::PERSISTING], true)) {
            $query->where('state', $request->string('state')->toString());
        }

        return $query;
    }
}
