<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Audit\Checks\Severity;

/**
 * Two runs side by side (§8 «Runs»), by fingerprint (decision 8): what the later one found that
 * the earlier did not (new), what both found (persisting), and what the earlier found that the
 * later did not (fixed) — fixed only for checks the later run actually ran, so a quick run after
 * a full one does not call every page finding fixed.
 *
 * Hidden findings are on neither side: hiding is a decision, not a change of the site.
 */
final class Comparison
{
    public const NEW = 'new';

    public const PERSISTING = 'persisting';

    public const FIXED = 'fixed';

    public const KINDS = [self::NEW, self::PERSISTING, self::FIXED];

    public function __construct(
        private readonly AuditRun $from,
        private readonly AuditRun $to,
    ) {}

    /**
     * One row per check that differs or persists, worst first, with the three counts.
     *
     * @return list<array{check: string, severity: string, new: int, persisting: int, fixed: int}>
     */
    public function summary(): array
    {
        $rows = [];

        foreach (self::KINDS as $kind) {
            $counted = $this->issues($kind)
                ->select(['check', 'severity'])
                ->selectRaw('count(*) as total')
                ->groupBy('check', 'severity')
                ->get();

            foreach ($counted as $row) {
                $check = (string) $row->getAttribute('check');
                $severity = (string) $row->getAttribute('severity');
                $rows[$check] ??= ['check' => $check, 'severity' => Severity::NOTICE, 'new' => 0, 'persisting' => 0, 'fixed' => 0];
                $rows[$check][$kind] += (int) $row->getAttribute('total');

                if (Severity::weight($severity) > Severity::weight($rows[$check]['severity'])) {
                    $rows[$check]['severity'] = $severity;
                }
            }
        }

        $rows = array_values($rows);

        usort($rows, static fn (array $a, array $b): int => [Severity::weight($b['severity']), $b['new'] + $b['fixed']] <=> [Severity::weight($a['severity']), $a['new'] + $a['fixed']]);

        return $rows;
    }

    /**
     * The findings of one kind: new and persisting ones from the later run, fixed ones from the
     * earlier — the run where they still were.
     *
     * @return Builder<AuditIssue>
     */
    public function issues(string $kind): Builder
    {
        if ($kind === self::FIXED) {
            /** @var list<string> $ran */
            $ran = $this->to->progress['checks'] ?? [];

            return AuditIssue::query()
                ->where('run_id', $this->from->id)
                ->whereNull('ignored_by')
                ->whereIn('check', $ran)
                // A recheck answers for its own addresses, not for every other page of the run.
                ->when($this->to->scope === AuditRun::URLS, fn (Builder $query) => $query->whereIn('url', $this->to->urls()))
                ->whereNotIn('fingerprint', $this->fingerprints($this->to, false));
        }

        $query = AuditIssue::query()->where('run_id', $this->to->id)->whereNull('ignored_by');

        return $kind === self::PERSISTING
            ? $query->whereIn('fingerprint', $this->fingerprints($this->from, true))
            : $query->whereNotIn('fingerprint', $this->fingerprints($this->from, true));
    }

    /**
     * The fingerprints of a run as a subquery — a run has thousands, and a list of them in the
     * query would be thousands of bindings.
     *
     * @return \Closure(QueryBuilder): void
     */
    private function fingerprints(AuditRun $run, bool $shownOnly): \Closure
    {
        return static function (QueryBuilder $query) use ($run, $shownOnly): void {
            $query->select('fingerprint')
                ->from((new AuditIssue)->getTable())
                ->where('run_id', $run->id)
                ->when($shownOnly, static fn (QueryBuilder $shown) => $shown->whereNull('ignored_by'));
        };
    }
}
