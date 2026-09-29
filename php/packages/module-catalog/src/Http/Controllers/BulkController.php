<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Catalog\Bulk\BulkActions;
use WebxUi\Catalog\Bulk\BulkRunner;
use WebxUi\Catalog\Models\BulkRun;

/**
 * Bulk actions of the product list (§11.4): which there are, start one, and how far one has got.
 *
 * A small one is done inside the request and answered `200` with the result and no id; a large
 * one is `202` with the run the panel polls.
 */
final class BulkController
{
    public function __construct(private readonly BulkRunner $runner) {}

    /** The actions the caller may start — the core's and the satellites' — with what each asks. */
    public function index(Request $request, BulkActions $actions): JsonResponse
    {
        return ApiResponse::data($actions->describe($this->can($request)));
    }

    /**
     * `{ action, params, selection: { ids } | { query: { q, state, facets } } }`.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:64'],
            'params' => ['nullable', 'array'],
            'selection' => ['required', 'array'],
            'selection.ids' => ['sometimes', 'array'],
            'selection.query' => ['sometimes', 'array'],
        ]);

        $user = $request->user();

        $run = $this->runner->start(
            (string) $validated['action'],
            (array) ($validated['params'] ?? []),
            (array) $validated['selection'],
            $user,
            $this->can($request),
        );

        return ApiResponse::data($run->toResponse($this->runner->labelOf($run)), $run->exists ? 202 : 200);
    }

    public function show(int $run): JsonResponse
    {
        $found = BulkRun::query()->find($run) ?? throw new NotFoundHttpException;

        return ApiResponse::data($found->toResponse($this->runner->labelOf($found)));
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
