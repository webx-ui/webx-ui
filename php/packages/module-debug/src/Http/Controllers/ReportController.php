<?php

declare(strict_types=1);

namespace WebxUi\Debug\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Debug\Reports;

/**
 * The details of a report, asked for by the bar when it is opened (§9 of the debug bar spec).
 *
 * Only to the administrator who made it: anybody else's id and an expired one are the same 404
 * — not a 403, which would confirm the id. Never cached anywhere: a report is values of somebody's
 * queries.
 */
final class ReportController
{
    /** `GET /api/cms/debug/reports` — this administrator's latest reports, newest first. */
    public function index(Request $request, Reports $reports): JsonResponse
    {
        return $this->private(new JsonResponse(['data' => $reports->recent($this->user($request))]));
    }

    /** `GET /api/cms/debug/reports/{id}` — one report, whole. */
    public function show(Request $request, Reports $reports, string $id): JsonResponse
    {
        $report = $reports->find($id, $this->user($request));

        if ($report === null) {
            throw new NotFoundHttpException;
        }

        return $this->private(new JsonResponse(['data' => $report], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function user(Request $request): int|string
    {
        $key = $request->user()?->getAuthIdentifier();

        return is_int($key) || is_string($key) ? $key : '';
    }

    private function private(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
