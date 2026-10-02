<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Audit\Fixes\FixRunner;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditRun;

/**
 * The fix button of the findings screen (§8): what the fixes of one finding would change, then
 * pressing one. The preview is asked first and shown; the press answers with the same preview
 * and `applied`.
 */
final class FixController
{
    public function __construct(private readonly FixRunner $runner) {}

    public function index(AuditRun $run, int $issue): JsonResponse
    {
        return ApiResponse::data($this->runner->offers($this->issue($run, $issue)));
    }

    public function store(Request $request, AuditRun $run, int $issue, string $fix): JsonResponse
    {
        $result = $this->runner->run($this->issue($run, $issue), $fix, $request->boolean('dry_run'));

        abort_if($result === null, 422, (string) __('webx-audit::screen.fix-unavailable'));

        return ApiResponse::data($result);
    }

    private function issue(AuditRun $run, int $id): AuditIssue
    {
        return AuditIssue::query()->where('run_id', $run->id)->findOrFail($id);
    }
}
