<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hiding and showing again (decision 9). A rule hides what it matches in every run kept — the
 * findings were found and stay stored, they only stop counting — and every run after it hides
 * the same the moment it is analysed. Removing a rule shows its findings again, unless another
 * rule still hides them.
 *
 * The counts of the runs it touched are taken again, so the overview and the history agree with
 * the findings screen at once rather than after the next run.
 */
final class Ignores
{
    public function __construct(private readonly Container $container) {}

    /** Hides, in this run, whatever a rule matches — the last thing a run does before counting. */
    public function apply(AuditRun $run): void
    {
        $rules = AuditIgnore::query()->orderBy('id')->get()->groupBy('check');

        foreach ($rules as $check => $ofCheck) {
            $this->hide(AuditIssue::query()->where('run_id', $run->id)->where('check', (string) $check), $ofCheck->all());
        }
    }

    public function add(string $check, string $pattern, string $reason, ?string $by): AuditIgnore
    {
        /** @var AuditIgnore $rule */
        $rule = AuditIgnore::query()->create([
            'check' => $check,
            'pattern' => trim($pattern),
            'reason' => trim($reason),
            'created_by' => $by === null ? null : mb_substr($by, 0, 64),
        ]);

        $runs = $this->hide(AuditIssue::query()->where('check', $check), [$rule]);
        $this->recount($runs);

        return $rule;
    }

    public function remove(AuditIgnore $rule): void
    {
        $runs = AuditIssue::query()->where('ignored_by', $rule->id)->distinct()->pluck('run_id')->map(static fn (mixed $id): int => (int) $id)->all();

        AuditIssue::query()->where('ignored_by', $rule->id)->update(['ignored_by' => null]);
        $rule->delete();

        // Another rule of the same check may still hide some of them.
        $others = AuditIgnore::query()->where('check', $rule->check)->orderBy('id')->get()->all();

        if ($others !== [] && $runs !== []) {
            $this->hide(AuditIssue::query()->whereIn('run_id', $runs)->where('check', $rule->check), $others);
        }

        $this->recount($runs);
    }

    /**
     * How many findings of the last finished run a rule would hide — what `dry_run` answers.
     */
    public function preview(string $check, string $pattern): int
    {
        $run = AuditRun::query()->siteWide()->where('status', AuditRun::DONE)->orderByDesc('id')->first();

        if ($run === null) {
            return 0;
        }

        $count = 0;

        foreach (AuditIssue::query()->where('run_id', $run->id)->where('check', $check)->whereNull('ignored_by')->lazyById(500, column: 'id') as $issue) {
            $count += Mask::matches($pattern, $issue->url) ? 1 : 0;
        }

        return $count;
    }

    /**
     * Marks what the rules match among the findings the query reaches and are not hidden yet.
     *
     * @param  Builder<AuditIssue>  $query
     * @param  list<AuditIgnore>  $rules  Of one check.
     * @return list<int> The runs that changed.
     */
    private function hide(Builder $query, array $rules): array
    {
        if ($rules === []) {
            return [];
        }

        /** @var array<int, list<int>> $byRule */
        $byRule = [];
        $runs = [];

        foreach ($query->whereNull('ignored_by')->select(['id', 'run_id', 'check', 'url'])->lazyById(500, column: 'id') as $issue) {
            foreach ($rules as $rule) {
                if ($rule->hides($issue->check, $issue->url)) {
                    $byRule[$rule->id][] = $issue->id;
                    $runs[$issue->run_id] = true;

                    break;
                }
            }
        }

        foreach ($byRule as $id => $issues) {
            foreach (array_chunk($issues, 500) as $chunk) {
                AuditIssue::query()->whereIn('id', $chunk)->update(['ignored_by' => $id]);
            }
        }

        return array_map('intval', array_keys($runs));
    }

    /**
     * @param  list<int>  $runs
     */
    private function recount(array $runs): void
    {
        // Through the container, not the constructor: the runner hides through this class too.
        $runner = $this->container->make(Runner::class);

        foreach (AuditRun::query()->whereIn('id', $runs)->where('status', AuditRun::DONE)->get() as $run) {
            $runner->recount($run);
        }
    }
}
