<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Audit\AuditSettings;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Content\ContentScanner;
use WebxUi\Audit\Contracts\AuditCheck;
use WebxUi\Audit\Crawl\Crawler;
use WebxUi\Audit\Crawl\Resources;
use WebxUi\Audit\Crawl\RobotsRules;
use WebxUi\Audit\Crawl\Seeder;
use WebxUi\Audit\Probes\Prober;
use WebxUi\Audit\Probes\ProbeSet;
use WebxUi\Audit\Probes\SiteClient;

/**
 * A run, stage by stage (§3), a piece at a time.
 *
 * `step()` does the next piece and says whether there is more: the queued job calls it once and
 * queues the next job, the console command calls it in a loop. What the stage has reached lives
 * in `progress`, so a worker that dies between pieces costs one piece, not the run.
 *
 * Stages, in order: `probes` (the config is read in the same piece — its checks need no
 * requests), `database`, `crawl` (the full scope only) and `analyse`. A check runs once, in the
 * last stage of the ones it needs.
 *
 * The crawl is four phases in `progress.crawl`: `seed` (home, sitemap, registry, robots.txt),
 * `fetch` (the queue, a few pages at a time, piece after piece), `resources` (the snapshot is
 * finished, then what the pages load and their external links are asked, the same way) and
 * `checks` (the checks of the crawl, one after another, as many as a piece has time for).
 */
final class Runner
{
    private const STAGES = ['probes', 'database', 'crawl', 'analyse'];

    /**
     * Panel modules that keep text in the database: installed without a content source, they are
     * what the overview lists as not searched (§7).
     */
    private const CONTENT_MODULES = [
        'pages', 'regions', 'articles', 'services', 'products', 'menus', 'banners', 'events',
        'recipes', 'vacancies', 'faq', 'team', 'press', 'reviews', 'tariffs',
    ];

    public function __construct(
        private readonly AuditChecks $checks,
        private readonly AuditSettings $settings,
        private readonly SiteClient $client,
        private readonly Prober $prober,
        private readonly ContentScanner $scanner,
        private readonly AuditContentSources $sources,
        private readonly ModuleRegistry $modules,
        private readonly Config $config,
        private readonly Seeder $seeder,
        private readonly Crawler $crawler,
        private readonly Resources $resources,
    ) {}

    public function start(string $scope, ?string $startedBy = null): AuditRun
    {
        /** @var AuditRun $run */
        $run = AuditRun::query()->create([
            'status' => AuditRun::QUEUED,
            'scope' => $scope,
            'base_url' => $this->settings->baseUrl(),
            'resolve_to' => $this->settings->resolveTo(),
            'started_by' => $startedBy,
            'pages_limit' => max(1, (int) $this->config->get('webx-audit.pages_limit', 1000)),
            'progress' => ['stage' => 'probes', 'done' => []],
        ]);

        return $run;
    }

    /** Every piece, one after another — the console, and the queue that turned out to be `sync`. */
    public function complete(AuditRun $run): AuditRun
    {
        while ($run->active() && $this->step($run, PHP_FLOAT_MAX)) {
            $run->refresh();
        }

        return $run->refresh();
    }

    /** The next piece of the run; true when there is more to do. */
    public function step(AuditRun $run, float $budget): bool
    {
        if (! $run->active()) {
            return false;
        }

        if ($run->status === AuditRun::QUEUED) {
            $run->update(['status' => AuditRun::RUNNING, 'started_at' => Carbon::now()]);
        }

        try {
            $stage = (string) ($run->progress['stage'] ?? 'probes');

            $finished = match ($stage) {
                'probes' => $this->probes($run),
                'database' => $this->database($run, $budget),
                'crawl' => $this->crawl($run, $budget),
                default => $this->analyse($run),
            };
        } catch (Throwable $failure) {
            $this->fail($run, $failure->getMessage());

            return false;
        }

        if (! $finished) {
            return true;
        }

        $next = $this->nextStage($run, $stage);

        if ($next === null) {
            return false;
        }

        $run->update(['progress' => [...($run->progress ?? []), 'stage' => $next, 'cursor' => null, 'done' => [...($run->progress['done'] ?? []), $stage]]]);

        return true;
    }

    public function fail(AuditRun $run, string $error): void
    {
        $run->update([
            'status' => AuditRun::FAILED,
            'error' => mb_substr($error, 0, 2000),
            'finished_at' => Carbon::now(),
        ]);
    }

    public function cancel(AuditRun $run): void
    {
        if ($run->active()) {
            $run->update(['status' => AuditRun::CANCELLED, 'finished_at' => Carbon::now()]);
        }
    }

    private function nextStage(AuditRun $run, string $stage): ?string
    {
        $index = array_search($stage, self::STAGES, true);
        $next = self::STAGES[is_int($index) ? $index + 1 : count(self::STAGES)] ?? null;

        if ($next === 'crawl' && $run->scope !== AuditRun::FULL) {
            return 'analyse';
        }

        return $next;
    }

    private function probes(AuditRun $run): bool
    {
        $client = $this->client->resolvingTo($run->resolve_to);
        $hosts = $this->settings->classifier($run->base_url);

        $this->runChecks($run, 'config', new AuditContext($run, $hosts, $client, new ProbeSet, $this->config), ['config']);

        $probes = $this->prober->collect($run->base_url, $client, $hosts);
        $run->update(['probes' => $probes->toArray()]);

        $this->runChecks($run, 'probes', new AuditContext($run, $hosts, $client, $probes, $this->config), ['config', 'probes']);

        return true;
    }

    private function database(AuditRun $run, float $budget): bool
    {
        $hosts = $this->settings->classifier($run->base_url);
        /** @var array{source?: int, skip?: int} $cursor */
        $cursor = (array) ($run->progress['cursor'] ?? []);

        $next = $this->scanner->scan($run, $hosts, $budget, $cursor);

        if ($next !== null) {
            $run->update(['progress' => [...($run->progress ?? []), 'cursor' => $next]]);

            return false;
        }

        $context = new AuditContext($run, $hosts, $this->client->resolvingTo($run->resolve_to), new ProbeSet, $this->config);
        $this->runChecks($run, 'database', $context, ['config', 'probes', 'database']);

        return true;
    }

    private function crawl(AuditRun $run, float $budget): bool
    {
        $deadline = microtime(true) + $budget;
        $hosts = $this->settings->classifier($run->base_url);
        $client = $this->client->resolvingTo($run->resolve_to);
        /** @var array{phase?: string, robots?: array{rules?: list<array{0: bool, 1: string}>, sitemaps?: list<string>}, check?: int} $state */
        $state = (array) ($run->progress['crawl'] ?? []);
        $phase = $state['phase'] ?? 'seed';

        if ($phase === 'seed') {
            $robots = $this->seeder->seed($run, $client, $hosts);
            $this->crawlState($run, ['phase' => 'fetch', 'robots' => $robots->toArray()]);

            return false;
        }

        if ($phase === 'fetch') {
            if (! $this->crawler->fetch($run, $client, $hosts, RobotsRules::fromArray($state['robots'] ?? []), $deadline)) {
                return false;
            }

            $this->crawler->finish($run);
            $this->resources->collect($run);
            $this->crawlState($run, ['phase' => 'resources']);

            return false;
        }

        if ($phase === 'resources') {
            if (! $this->resources->check($run, $client, $deadline)) {
                return false;
            }

            $this->resources->finish($run);
            $this->crawlState($run, ['phase' => 'checks', 'check' => 0]);

            return false;
        }

        $collected = ['config', 'probes', 'database', 'crawl'];
        $checks = $this->checks->runnable($collected, 'crawl');
        $context = new AuditContext($run, $hosts, $client, new ProbeSet, $this->config);
        $first = $state['check'] ?? 0;

        for ($index = $first; $index < count($checks); $index++) {
            // At least one check a piece, however slow, so a run always moves.
            if ($index > $first && microtime(true) >= $deadline) {
                $this->crawlState($run, ['check' => $index]);

                return false;
            }

            $this->runChecks($run, 'crawl', $context, $collected, [$checks[$index]]);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function crawlState(AuditRun $run, array $changes): void
    {
        $run->update(['progress' => [...($run->progress ?? []), 'crawl' => [...(array) ($run->progress['crawl'] ?? []), ...$changes]]]);
    }

    /**
     * Fingerprints against the previous run of the scope, the counters, the health — and done.
     */
    private function analyse(AuditRun $run): bool
    {
        $previous = $run->previous();
        $before = $previous === null
            ? []
            : AuditIssue::query()->where('run_id', $previous->id)->pluck('check', 'fingerprint')->all();

        if ($before !== []) {
            AuditIssue::query()
                ->where('run_id', $run->id)
                ->whereIn('fingerprint', array_keys($before))
                ->update(['state' => AuditIssue::PERSISTING]);
        }

        /** @var list<string> $ran */
        $ran = $run->progress['checks'] ?? [];
        $now = AuditIssue::query()->where('run_id', $run->id)->pluck('fingerprint')->flip()->all();
        $fixed = 0;

        foreach ($before as $fingerprint => $check) {
            if (! isset($now[$fingerprint]) && in_array($check, $ran, true)) {
                $fixed++;
            }
        }

        $run->update([
            'status' => AuditRun::DONE,
            'finished_at' => Carbon::now(),
            'counts' => [...$this->counts($run, $ran), 'fixed' => $fixed, 'previous_id' => $previous?->id],
        ]);

        $this->pruneSnapshots();

        return true;
    }

    /**
     * Page snapshots for the last few full runs only (decision 8) — they are the bulk of the
     * module's tables. The findings stay: "fixed" and the history read those.
     */
    private function pruneSnapshots(): void
    {
        $keep = max(1, (int) $this->config->get('webx-audit.keep_snapshots', 5));

        $old = AuditRun::query()
            ->where('scope', AuditRun::FULL)
            ->whereIn('status', [AuditRun::DONE, AuditRun::FAILED, AuditRun::CANCELLED])
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id')
            ->all();

        if ($old !== []) {
            AuditLink::query()->whereIn('run_id', $old)->delete();
            AuditResource::query()->whereIn('run_id', $old)->delete();
            AuditPage::query()->whereIn('run_id', $old)->delete();
        }
    }

    /**
     * @param  list<string>  $ran
     * @return array<string, mixed>
     */
    private function counts(AuditRun $run, array $ran): array
    {
        $issues = AuditIssue::query()->where('run_id', $run->id)->whereNull('ignored_by')->get(['check', 'severity', 'state']);

        $severity = array_fill_keys(Severity::ALL, 0);
        $groups = [];
        $worst = [];

        foreach ($issues as $issue) {
            $severity[$issue->severity] = ($severity[$issue->severity] ?? 0) + 1;

            $group = $this->checks->get($issue->check)?->group() ?? 'other';
            $groups[$group] ??= array_fill_keys(Severity::ALL, 0);
            $groups[$group][$issue->severity]++;

            if (Severity::weight($issue->severity) > Severity::weight($worst[$issue->check] ?? '')) {
                $worst[$issue->check] = $issue->severity;
            }
        }

        $total = 0;
        $lost = 0;

        foreach ($ran as $id) {
            $check = $this->checks->get($id);
            $total += Severity::weight($check?->severity() ?? Severity::NOTICE);
            $lost += isset($worst[$id]) ? Severity::weight($worst[$id]) : 0;
        }

        return [
            'severity' => $severity,
            'groups' => $groups,
            'checks' => $ran,
            'failed' => $worst,
            'health' => $total === 0 ? 100 : (int) round(100 * max(0, $total - $lost) / $total),
            'new' => $issues->where('state', AuditIssue::NEW)->count(),
            'sources' => $this->sourcesSummary(),
        ];
    }

    /**
     * Which content modules were searched and which are installed without a source.
     *
     * @return array{searched: list<string>, missing: list<string>}
     */
    private function sourcesSummary(): array
    {
        $searched = array_keys($this->sources->all());
        $missing = array_values(array_filter(
            self::CONTENT_MODULES,
            fn (string $id): bool => $this->modules->has($id) && ! in_array($id, $searched, true),
        ));

        return ['searched' => $searched, 'missing' => $missing];
    }

    /**
     * Runs the checks of a stage and stores what they found — after removing what the same checks
     * stored in this run before, so a piece the queue retries does not count twice.
     *
     * @param  list<string>  $collected
     * @param  list<AuditCheck>|null  $only  Some of the stage's checks, when a piece cannot run them all.
     */
    private function runChecks(AuditRun $run, string $stage, AuditContext $context, array $collected, ?array $only = null): void
    {
        $checks = $only ?? $this->checks->runnable($collected, $stage);
        $ids = array_map(static fn (AuditCheck $check): string => $check->id(), $checks);

        if ($ids === []) {
            return;
        }

        AuditIssue::query()->where('run_id', $run->id)->whereIn('check', $ids)->delete();

        $now = Carbon::now();
        $rows = [];

        foreach ($checks as $check) {
            foreach ($check->run($context) as $finding) {
                $rows[] = $this->row($run, $finding, $now);
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            AuditIssue::query()->insert($chunk);
        }

        $ran = array_values(array_unique([...($run->progress['checks'] ?? []), ...$ids]));
        $run->update(['progress' => [...($run->progress ?? []), 'checks' => $ran]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AuditRun $run, Finding $finding, Carbon $now): array
    {
        return [
            'run_id' => $run->id,
            'check' => $finding->check,
            'severity' => Severity::valid($finding->severity) ? $finding->severity : Severity::NOTICE,
            'page_id' => $finding->pageId,
            'url' => $finding->url === null ? null : mb_substr($finding->url, 0, 2048),
            'details' => json_encode($finding->details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'fingerprint' => $finding->fingerprint(),
            'state' => AuditIssue::NEW,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
