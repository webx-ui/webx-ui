<?php

declare(strict_types=1);

namespace WebxUi\Audit\Console;

use Illuminate\Console\Command;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\Runner;

/**
 * The audit from a terminal, in this process, without a queue (§7).
 *
 * `--quick --fail-on=error` is the deploy step after the migrations: a site with links to a
 * development stand or with `APP_DEBUG=true` does not go out silently. It is also the way to run
 * the audit on a host whose queue is `sync`, where the panel will not start one.
 */
final class RunCommand extends Command
{
    protected $signature = 'webx:audit:run
        {--quick : The config, the probes and the database only — seconds, not minutes}
        {--fail-on= : Exit with 1 when a finding is at least this bad: error, warning or notice}';

    protected $description = 'Run the site audit here and now, and print what it found';

    public function handle(Runner $runner): int
    {
        $failOn = $this->option('fail-on');

        if (is_string($failOn) && $failOn !== '' && ! Severity::valid($failOn)) {
            $this->components->error('--fail-on takes error, warning or notice.');

            return self::INVALID;
        }

        $run = $runner->start($this->option('quick') ? AuditRun::QUICK : AuditRun::FULL, 'console');
        $this->components->info("Auditing {$run->base_url} ({$run->scope})…");

        $run = $runner->complete($run);

        if ($run->status !== AuditRun::DONE) {
            $this->components->error('The audit did not finish: '.($run->error ?? $run->status));

            return self::FAILURE;
        }

        $issues = AuditIssue::query()->where('run_id', $run->id)->whereNull('ignored_by')->orderBy('id')->get();

        $this->table(
            ['Severity', 'Check', 'Address', 'What'],
            $issues->map(fn (AuditIssue $issue): array => [
                $issue->severity,
                $issue->check,
                (string) $issue->url,
                $this->summaryOf($issue),
            ])->sortBy(static fn (array $row): int => -Severity::weight($row[0]))->values()->all(),
        );

        /** @var array{severity?: array<string, int>, health?: int} $counts */
        $counts = $run->counts ?? [];
        $by = $counts['severity'] ?? [];

        $this->line(sprintf(
            'Health %d%% · errors %d · warnings %d · notices %d',
            $counts['health'] ?? 100,
            $by[Severity::ERROR] ?? 0,
            $by[Severity::WARNING] ?? 0,
            $by[Severity::NOTICE] ?? 0,
        ));

        if (is_string($failOn) && $failOn !== '' && $issues->contains(static fn (AuditIssue $issue): bool => Severity::reaches($issue->severity, $failOn))) {
            $this->components->error("Findings at the level of {$failOn} or worse.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function summaryOf(AuditIssue $issue): string
    {
        $summary = $issue->details['summary'] ?? null;

        if (! is_array($summary) || ! is_string($summary['key'] ?? null)) {
            return '';
        }

        /** @var array<string, scalar|null> $params */
        $params = is_array($summary['params'] ?? null) ? $summary['params'] : [];

        return (string) __($summary['key'], array_map(static fn (mixed $value): string => (string) $value, $params));
    }
}
