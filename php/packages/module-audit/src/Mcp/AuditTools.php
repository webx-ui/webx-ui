<?php

declare(strict_types=1);

namespace WebxUi\Audit\Mcp;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use WebxUi\Audit\AuditSettings;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\CheckTexts;
use WebxUi\Audit\Contracts\AuditCheck;
use WebxUi\Audit\Fixes\AuditFixes;
use WebxUi\Audit\Fixes\FixRunner;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Http\Controllers\HostController;
use WebxUi\Audit\Http\Controllers\IssueController;
use WebxUi\Audit\Http\Controllers\PageController;
use WebxUi\Audit\Http\Controllers\RunController;
use WebxUi\Audit\Http\Resources\IssueResource;
use WebxUi\Audit\Http\Resources\RunResource;
use WebxUi\Audit\Runs\AuditIgnore;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\Ignores;
use WebxUi\Audit\Runs\RunAuditStage;
use WebxUi\Audit\Runs\Runner;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Tool;

/**
 * The audit for an agent (§9): start a run, follow it, read what it found page by page or check
 * by check, see where the site links to, and fix — with `dry_run` first.
 *
 * The reading tools answer exactly what the panel's screens answer: they call the same
 * controllers with the agent's arguments as the query, so a filter that works on the screen
 * works here, and the two cannot drift apart.
 */
final class AuditTools
{
    private const SCOPE_READ = 'audit:read';

    private const SCOPE_WRITE = 'audit:write';

    /**
     * @return list<Tool>
     */
    public static function all(): array
    {
        $run = ['type' => 'integer', 'description' => 'A run id; the last finished run when left out'];

        return [
            Tool::mutating(
                'run',
                'Start an audit run in the queue. "quick" checks the config, the host and the database in seconds; "full" also crawls the site and checks every page; "urls" rechecks the pages given in "urls" after an edit and says which of their findings the last full run had are fixed. Follow it with audit_status.',
                static fn (array $arguments): array => self::start($arguments),
                [
                    'properties' => [
                        'scope' => ['type' => 'string', 'enum' => AuditRun::SCOPES, 'default' => AuditRun::QUICK],
                        'urls' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => AuditRun::URLS_LIMIT, 'description' => 'Absolute addresses on the audited site, for the "urls" scope'],
                    ],
                ],
                scope: self::SCOPE_WRITE,
                permission: ['audit.run', 'audit.manage'],
            ),

            Tool::read(
                'status',
                'The run that is going, if any, and the last finished one: stage, progress, counts by severity and group, and the health in percent — the share of crawled pages without errors, minus 10 per site-wide error check and 2 per warning check (at most 20); health_parts has the pieces.',
                static fn (array $arguments): array => self::status(),
                scope: self::SCOPE_READ,
            ),

            Tool::read(
                'issues',
                'What a run found. Without "check": one row per check with its counts and its three texts (found, why, fix) — start here. With "check": the findings of that check, each with its address and details.',
                static fn (array $arguments): array => self::issues($arguments),
                [
                    'properties' => [
                        'run' => $run,
                        'check' => ['type' => 'string', 'description' => 'A check id, e.g. title.duplicate'],
                        'severity' => ['type' => 'string', 'enum' => ['error', 'warning', 'notice']],
                        'group' => ['type' => 'string'],
                        'state' => ['type' => 'string', 'enum' => [AuditIssue::NEW, AuditIssue::PERSISTING, IssueController::HIDDEN]],
                        'page' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
                scope: self::SCOPE_READ,
            ),

            Tool::read(
                'pages',
                'The pages of the last full run with their snapshot: status, title, description, H1, canonical, words, links. Filter any field as f[field]=operation:value (contains, eq, empty, filled, gt, lt, yes, no).',
                static fn (array $arguments): array => self::pages($arguments),
                [
                    'properties' => [
                        'run' => $run,
                        'search' => ['type' => 'string', 'description' => 'Part of the address'],
                        'status' => ['type' => 'string', 'description' => '2xx, 3xx, 4xx or 5xx'],
                        'indexable' => ['type' => 'boolean'],
                        'check' => ['type' => 'string', 'description' => 'Only pages with a finding of this check'],
                        'f' => ['type' => 'object', 'description' => 'field => "operation:value"'],
                        'sort' => ['type' => 'string', 'description' => 'A field, "-field" for descending'],
                        'page' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
                scope: self::SCOPE_READ,
            ),

            Tool::read(
                'page_get',
                'One crawled page by id or address: the whole snapshot, its headers, headings, hreflang, JSON-LD and its findings.',
                static fn (array $arguments): array => self::page($arguments),
                [
                    'properties' => [
                        'run' => $run,
                        'id' => ['type' => 'integer'],
                        'url' => ['type' => 'string', 'description' => 'The absolute address as crawled'],
                    ],
                ],
                scope: self::SCOPE_READ,
            ),

            Tool::read(
                'hosts',
                'Every host the site points at, by class — own, own_mirror, dev (a development stand), external: how many links and pages in the last full run, and how many fields in the database, with where. Stands first.',
                static fn (array $arguments): array => self::hosts($arguments),
                [
                    'properties' => [
                        'class' => ['type' => 'string', 'enum' => [HostClassifier::OWN, HostClassifier::OWN_MIRROR, HostClassifier::DEV, HostClassifier::EXTERNAL]],
                        'host' => ['type' => 'string', 'description' => 'One host: its places in pages and in the database'],
                        'search' => ['type' => 'string', 'description' => 'Part of a host name'],
                    ],
                ],
                scope: self::SCOPE_READ,
            ),

            Tool::mutating(
                'fix',
                'Fix one finding with a fix the module that owns the thing offers. Without "fix": the fixes it has and what each would change. With "fix" and dry_run: the preview. Without dry_run: applied; the finding stays until the next run confirms it.',
                static fn (array $arguments): array => self::fix($arguments),
                [
                    'properties' => [
                        'issue' => ['type' => 'integer', 'description' => 'A finding id, from audit_issues with a check'],
                        'fix' => ['type' => 'string', 'description' => 'A fix id, e.g. audit.replace-host'],
                    ],
                    'required' => ['issue'],
                ],
                scope: self::SCOPE_WRITE,
                permission: 'audit.manage',
            ),

            Tool::mutating(
                'ignore',
                'Hide findings on purpose — noindex on the search page, a long title on a landing — with the reason: a check, an address or a mask (`*` a stretch without a slash, `**` with them; empty for every finding of the check). Hidden findings stop counting in this and every later run. Without "check" and "remove": the rules. With dry_run: how many findings of the last run it would hide. "remove": a rule id, shown again.',
                static fn (array $arguments): array => self::ignore($arguments),
                [
                    'properties' => [
                        'check' => ['type' => 'string', 'description' => 'A check id, e.g. indexing.noindex'],
                        'pattern' => ['type' => 'string', 'description' => 'An address or a path mask, e.g. /search/**; empty hides the whole check'],
                        'reason' => ['type' => 'string', 'description' => 'Why it is all right — required'],
                        'remove' => ['type' => 'integer', 'description' => 'The id of a rule to remove'],
                    ],
                ],
                scope: self::SCOPE_WRITE,
                permission: 'audit.manage',
            ),
        ];
    }

    /**
     * The catalogue of checks: what each looks at and its three texts, so an agent fixes the
     * cause and not the symptom (§9).
     *
     * @return list<McpResource>
     */
    public static function resources(): array
    {
        return [
            new McpResource(
                'audit://checks',
                'Audit checks',
                'Every check of the site audit: its id, group, worst severity, what it needs collected, what it finds, why it matters, how to fix it, and the fixes that can close it.',
                static function (): array {
                    $fixes = app(AuditFixes::class);

                    return ['checks' => array_values(array_map(
                        static fn (AuditCheck $check): array => [
                            'id' => $check->id(),
                            'group' => $check->group(),
                            'severity' => $check->severity(),
                            'needs' => $check->needs(),
                            ...CheckTexts::of($check),
                            'fixes' => $fixes->forCheck($check->id()),
                        ],
                        app(AuditChecks::class)->all(),
                    ))];
                },
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function start(array $arguments): array
    {
        $scope = is_string($arguments['scope'] ?? null) ? $arguments['scope'] : AuditRun::QUICK;

        if (! in_array($scope, AuditRun::SCOPES, true)) {
            throw new ToolFailure('The scope is "quick", "full" or "urls".');
        }

        $config = app(Config::class);
        $connection = (string) $config->get('queue.default', 'sync');

        if ($config->get("queue.connections.{$connection}.driver", $connection) === 'sync') {
            throw new ToolFailure((string) __('webx-audit::page.sync-queue'));
        }

        if (AuditRun::query()->active()->exists()) {
            throw new ToolFailure((string) __('webx-audit::page.already-running'));
        }

        $urls = [];

        if ($scope === AuditRun::URLS) {
            $urls = RunController::ownUrls((array) ($arguments['urls'] ?? []), app(AuditSettings::class)->baseUrl());

            if ($urls === []) {
                throw new ToolFailure((string) __('webx-audit::page.urls-foreign'));
            }
        }

        if ($arguments[Tool::DRY_RUN] ?? false) {
            return ['started' => false, 'scope' => $scope, 'urls' => $urls];
        }

        $run = app(Runner::class)->start($scope, 'mcp', $urls);
        app(Dispatcher::class)->dispatch(new RunAuditStage($run->id));

        return ['started' => true, 'run' => self::json(new RunResource($run->refresh()))];
    }

    /**
     * @return array<string, mixed>
     */
    private static function status(): array
    {
        $active = AuditRun::query()->active()->orderByDesc('id')->first();
        $done = AuditRun::query()->siteWide()->where('status', AuditRun::DONE)->orderByDesc('id')->first();

        return [
            'active' => $active === null ? null : self::json(new RunResource($active)),
            'done' => $done === null ? null : self::json(new RunResource($done)),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function issues(array $arguments): array
    {
        $run = self::run($arguments, false);
        $request = self::request($arguments, ['severity', 'group', 'state', 'check', 'page']);
        $controller = app(IssueController::class);

        if (! is_string($arguments['check'] ?? null)) {
            return ['run' => $run->id, 'checks' => self::data($controller->checks($request, $run))];
        }

        $response = $controller->index($request, $run)->response($request);

        return ['run' => $run->id, ...self::data($response, true)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function pages(array $arguments): array
    {
        $run = self::run($arguments, true);
        $request = self::request($arguments, ['search', 'status', 'indexable', 'check', 'f', 'sort', 'page']);

        return ['run' => $run->id, ...self::data(app(PageController::class)->index($request, $run), true)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function page(array $arguments): array
    {
        $run = self::run($arguments, true);
        $query = AuditPage::query()->where('run_id', $run->id);

        $page = match (true) {
            is_numeric($arguments['id'] ?? null) => $query->whereKey((int) $arguments['id'])->first(),
            is_string($arguments['url'] ?? null) => $query->where('url_hash', AuditPage::hash((string) $arguments['url']))->first(),
            default => throw new ToolFailure('Give the page an id or a url.'),
        };

        if (! $page instanceof AuditPage) {
            throw new ToolFailure('No such page in run '.$run->id.'.');
        }

        return self::data(app(PageController::class)->show($run, $page));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function hosts(array $arguments): array
    {
        $query = array_intersect_key($arguments, array_flip(['class', 'host', 'search']));

        // One host means its places, and the list narrowed to it — what the screen's row opens.
        if (is_string($query['host'] ?? null)) {
            $query['search'] = $query['host'];
        }

        $answer = self::data(app(HostController::class)->index(Request::create('/', 'GET', $query)));

        if (is_string($query['host'] ?? null)) {
            $answer['hosts'] = array_values(array_filter(
                (array) ($answer['hosts'] ?? []),
                static fn (mixed $row): bool => is_array($row) && ($row['host'] ?? null) === strtolower((string) $query['host']),
            ));
        }

        return $answer;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function ignore(array $arguments): array
    {
        $ignores = app(Ignores::class);

        if (is_numeric($arguments['remove'] ?? null)) {
            $rule = AuditIgnore::query()->find((int) $arguments['remove']);

            if (! $rule instanceof AuditIgnore) {
                throw new ToolFailure('No hiding rule with that id.');
            }

            if (! ($arguments[Tool::DRY_RUN] ?? false)) {
                $ignores->remove($rule);
            }

            return ['removed' => $rule->toPanel(), 'applied' => ! ($arguments[Tool::DRY_RUN] ?? false)];
        }

        if (! is_string($arguments['check'] ?? null)) {
            return ['rules' => AuditIgnore::query()->orderByDesc('id')->get()->map(static fn (AuditIgnore $rule): array => $rule->toPanel())->all()];
        }

        $check = $arguments['check'];
        $pattern = is_string($arguments['pattern'] ?? null) ? trim($arguments['pattern']) : '';
        $reason = is_string($arguments['reason'] ?? null) ? trim($arguments['reason']) : '';

        if (app(AuditChecks::class)->get($check) === null) {
            throw new ToolFailure('No check with that id. The catalogue is the audit://checks resource.');
        }

        if ($reason === '') {
            throw new ToolFailure('Say why in "reason": the next person to open the findings will want to know.');
        }

        if ($arguments[Tool::DRY_RUN] ?? false) {
            return ['applied' => false, 'hidden' => $ignores->preview($check, $pattern)];
        }

        return ['applied' => true, 'rule' => $ignores->add($check, $pattern, $reason, 'mcp')->toPanel()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function fix(array $arguments): array
    {
        $issue = AuditIssue::query()->find(is_numeric($arguments['issue'] ?? null) ? (int) $arguments['issue'] : 0);

        if (! $issue instanceof AuditIssue) {
            throw new ToolFailure('No finding with that id.');
        }

        $runner = app(FixRunner::class);

        if (! is_string($arguments['fix'] ?? null)) {
            return ['issue' => $issue->id, 'check' => $issue->check, 'fixes' => $runner->offers($issue)];
        }

        $result = $runner->run($issue, $arguments['fix'], (bool) ($arguments[Tool::DRY_RUN] ?? false));

        if ($result === null) {
            throw new ToolFailure('That fix cannot close this finding. Call audit_fix without "fix" for the ones that can.');
        }

        return ['issue' => $issue->id, ...$result];
    }

    /**
     * The run named, or the last finished one — of a full scope where pages are wanted.
     *
     * @param  array<string, mixed>  $arguments
     */
    private static function run(array $arguments, bool $full): AuditRun
    {
        $query = AuditRun::query();

        $run = is_numeric($arguments['run'] ?? null)
            ? $query->find((int) $arguments['run'])
            : $query->siteWide()->where('status', AuditRun::DONE)->when($full, static fn ($q) => $q->where('scope', AuditRun::FULL))->orderByDesc('id')->first();

        if (! $run instanceof AuditRun) {
            throw new ToolFailure($full ? 'There is no finished full run yet. Start one with audit_run scope "full".' : 'There is no finished run yet. Start one with audit_run.');
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $keys
     */
    private static function request(array $arguments, array $keys): Request
    {
        $query = array_intersect_key($arguments, array_flip($keys));

        if (is_bool($query['indexable'] ?? null)) {
            $query['indexable'] = $query['indexable'] ? '1' : '0';
        }

        // The paginator reads the page from the request of the call, not from this one.
        $page = is_numeric($query['page'] ?? null) ? max(1, (int) $query['page']) : 1;
        Paginator::currentPageResolver(static fn (): int => $page);

        return Request::create('/', 'GET', $query);
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(JsonResponse $response, bool $whole = false): array
    {
        $data = $response->getData(true);

        if (! is_array($data)) {
            return [];
        }

        return $whole ? $data : (is_array($data['data'] ?? null) ? $data['data'] : []);
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(RunResource|IssueResource $resource): array
    {
        return $resource->toArray(Request::create('/'));
    }
}
