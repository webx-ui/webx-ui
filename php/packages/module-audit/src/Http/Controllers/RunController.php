<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Controllers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Audit\Http\Resources\RunResource;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\RunAuditStage;
use WebxUi\Audit\Runs\Runner;

/**
 * The runs: the list, the latest one for the overview, starting one and cancelling it.
 */
final class RunController
{
    public function __construct(
        private readonly Runner $runner,
        private readonly Config $config,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $runs = AuditRun::query()
            ->orderByDesc('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return RunResource::collection($runs);
    }

    /**
     * What the overview opens on: the run that is going, or the last one that finished, and the
     * one before it for "new" and "fixed" — plus whether the panel can start a run at all.
     */
    public function latest(): JsonResponse
    {
        /** @var AuditRun|null $active */
        $active = AuditRun::query()->active()->orderByDesc('id')->first();
        /** @var AuditRun|null $done */
        $done = AuditRun::query()->where('status', AuditRun::DONE)->orderByDesc('id')->first();
        /** @var AuditRun|null $last */
        $last = AuditRun::query()->orderByDesc('id')->first();
        /** @var AuditRun|null $crawled */
        $crawled = AuditRun::query()->where('status', AuditRun::DONE)->where('scope', AuditRun::FULL)->orderByDesc('id')->first();

        return ApiResponse::data([
            'active' => $active === null ? null : new RunResource($active),
            'done' => $done === null ? null : new RunResource($done),
            // The pages screen reads the last full run: a quick one has no pages.
            'crawled' => $crawled === null ? null : new RunResource($crawled),
            // A run that failed or was cancelled after the last good one — said, not hidden.
            'last' => $last === null || $last->is($done) || $last->is($active) ? null : new RunResource($last),
            'queue' => ['sync' => $this->syncQueue()],
        ]);
    }

    public function show(AuditRun $run): JsonResponse
    {
        return ApiResponse::data(new RunResource($run));
    }

    /**
     * A run in the queue (decision 5). On a `sync` queue it would run inside this request for as
     * long as it takes, so the panel refuses and names the command that does it instead.
     */
    public function store(Request $request, Dispatcher $bus): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'string', Rule::in(AuditRun::SCOPES)],
        ]);

        if ($this->syncQueue()) {
            return ApiResponse::message((string) __('webx-audit::page.sync-queue'), 409);
        }

        if (AuditRun::query()->active()->exists()) {
            return ApiResponse::message((string) __('webx-audit::page.already-running'), 409);
        }

        $user = $request->user('cms') ?? $request->user();
        $run = $this->runner->start((string) $validated['scope'], $user === null ? null : (string) $user->getAuthIdentifier());

        $bus->dispatch(new RunAuditStage($run->id));

        return ApiResponse::data(new RunResource($run->refresh()), 201);
    }

    public function cancel(AuditRun $run): JsonResponse
    {
        $this->runner->cancel($run);

        return ApiResponse::data(new RunResource($run->refresh()));
    }

    private function syncQueue(): bool
    {
        $connection = (string) $this->config->get('queue.default', 'sync');

        return $this->config->get("queue.connections.{$connection}.driver", $connection) === 'sync';
    }
}
