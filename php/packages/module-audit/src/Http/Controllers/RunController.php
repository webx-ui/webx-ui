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
use WebxUi\Audit\AuditSettings;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\CheckTexts;
use WebxUi\Audit\Http\Resources\IssueResource;
use WebxUi\Audit\Http\Resources\RunResource;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\Comparison;
use WebxUi\Audit\Runs\RunAuditStage;
use WebxUi\Audit\Runs\Runner;

/**
 * The runs: the list, the latest one for the overview, starting one and cancelling it, two of
 * them compared, and all of them cleared.
 */
final class RunController
{
    public function __construct(
        private readonly Runner $runner,
        private readonly Config $config,
        private readonly AuditChecks $checks,
        private readonly AuditSettings $settings,
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
        $done = AuditRun::query()->siteWide()->where('status', AuditRun::DONE)->orderByDesc('id')->first();
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
            'urls' => ['required_if:scope,'.AuditRun::URLS, 'array', 'max:'.AuditRun::URLS_LIMIT],
            'urls.*' => ['string', 'max:2048'],
        ]);

        if ($this->syncQueue()) {
            return ApiResponse::message((string) __('webx-audit::page.sync-queue'), 409);
        }

        if (AuditRun::query()->active()->exists()) {
            return ApiResponse::message((string) __('webx-audit::page.already-running'), 409);
        }

        $scope = (string) $validated['scope'];
        $urls = $scope === AuditRun::URLS ? self::ownUrls((array) $validated['urls'], $this->settings->baseUrl()) : [];

        if ($scope === AuditRun::URLS && $urls === []) {
            return ApiResponse::message((string) __('webx-audit::page.urls-foreign'), 422);
        }

        $user = $request->user('cms') ?? $request->user();
        $run = $this->runner->start($scope, $user === null ? null : (string) $user->getAuthIdentifier(), $urls);

        $bus->dispatch(new RunAuditStage($run->id));

        return ApiResponse::data(new RunResource($run->refresh()), 201);
    }

    /**
     * Two runs compared by fingerprint: one row per check with what is new in `to`, what both
     * have and what `from` had that `to` no longer has. Without `from`, `to` is compared with the
     * run it was analysed against.
     */
    public function compare(Request $request): JsonResponse
    {
        [$from, $to] = $this->pair($request);
        $rows = (new Comparison($from, $to))->summary();

        return ApiResponse::data([
            'from' => new RunResource($from),
            'to' => new RunResource($to),
            'checks' => array_map(function (array $row): array {
                $check = $this->checks->get($row['check']);

                return [...$row, 'title' => $check === null ? $row['check'] : CheckTexts::of($check)['title']];
            }, $rows),
        ]);
    }

    /** The findings of one check and one kind (`new`, `persisting`, `fixed`) of a comparison. */
    public function compareIssues(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'kind' => ['required', Rule::in(Comparison::KINDS)],
            'check' => ['nullable', 'string', 'max:64'],
        ]);

        [$from, $to] = $this->pair($request);

        $query = (new Comparison($from, $to))
            ->issues($request->string('kind')->toString())
            ->when($request->filled('check'), static fn ($query) => $query->where('check', $request->string('check')->toString()))
            ->orderBy('id');

        return IssueResource::collection($query->paginate(min(200, max(1, $request->integer('per_page', 50)))));
    }

    /**
     * @return array{0: AuditRun, 1: AuditRun}
     */
    private function pair(Request $request): array
    {
        $request->validate([
            'to' => ['required', 'integer'],
            'from' => ['nullable', 'integer'],
        ]);

        $to = AuditRun::query()->findOrFail($request->integer('to'));
        $from = $request->filled('from') ? AuditRun::query()->findOrFail($request->integer('from')) : $to->previous();

        abort_if($from === null, 404);

        return [$from, $to];
    }

    /**
     * The addresses of a recheck that belong to the audited site — a recheck does not knock on
     * somebody else's door.
     *
     * @param  array<mixed>  $urls
     * @return list<string>
     */
    public static function ownUrls(array $urls, string $base): array
    {
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        $own = [];

        foreach ($urls as $url) {
            if (is_string($url) && $host !== '' && strtolower((string) parse_url(trim($url), PHP_URL_HOST)) === $host) {
                $own[] = trim($url);
            }
        }

        return array_slice(array_values(array_unique($own)), 0, AuditRun::URLS_LIMIT);
    }

    public function cancel(AuditRun $run): JsonResponse
    {
        $this->runner->cancel($run);

        return ApiResponse::data(new RunResource($run->refresh()));
    }

    /**
     * Every run and all it found, at once. Refused while a run is going: its job would keep
     * writing rows for a run that is no longer there.
     */
    public function clear(): JsonResponse
    {
        if (AuditRun::query()->active()->exists()) {
            return ApiResponse::message((string) __('webx-audit::page.clear-running'), 409);
        }

        return ApiResponse::data(['runs' => $this->runner->clear()]);
    }

    private function syncQueue(): bool
    {
        $connection = (string) $this->config->get('queue.default', 'sync');

        return $this->config->get("queue.connections.{$connection}.driver", $connection) === 'sync';
    }
}
