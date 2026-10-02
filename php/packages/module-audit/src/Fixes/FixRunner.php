<?php

declare(strict_types=1);

namespace WebxUi\Audit\Fixes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Audit\Contracts\AuditFix;
use WebxUi\Audit\Runs\AuditIssue;

/**
 * What the button and `audit_fix` share: which fixes a finding has, what each would change, and
 * pressing one. A finding is never closed by the fix — the next run decides that — it is only
 * marked with what was pressed, so the screen can say "fixed, run again to confirm".
 */
final readonly class FixRunner
{
    public function __construct(private AuditFixes $fixes) {}

    /**
     * Every fix that can close this finding now, with its preview. A fix whose preview is
     * empty — nothing left to change — is not offered.
     *
     * @return list<array{id: string, title: string, description: string, changes: list<array<string, mixed>>, total: int, note: string|null}>
     */
    public function offers(AuditIssue $issue): array
    {
        $finding = $issue->finding();
        $offers = [];

        foreach ($this->fixes->available($finding) as $fix) {
            $preview = $fix->preview($finding);

            if (! $preview->empty()) {
                $offers[] = ['id' => $fix->id(), ...FixTexts::of($fix), ...$preview->toArray()];
            }
        }

        return $offers;
    }

    /**
     * The fix's preview, and — unless `$dryRun` — the fix applied and the finding marked. Null
     * when that fix cannot close this finding.
     *
     * @return array{id: string, applied: bool, changes: list<array<string, mixed>>, total: int, note: string|null}|null
     */
    public function run(AuditIssue $issue, string $id, bool $dryRun): ?array
    {
        $finding = $issue->finding();
        $fix = $this->fixes->get($id);

        if (! $fix instanceof AuditFix || ! in_array($finding->check, $fix->fixes(), true) || ! $fix->available($finding)) {
            return null;
        }

        $preview = $fix->preview($finding);

        if ($dryRun || $preview->empty()) {
            return ['id' => $id, 'applied' => false, ...$preview->toArray()];
        }

        DB::transaction(static function () use ($fix, $finding, $issue, $id): void {
            $fix->apply($finding);
            $issue->forceFill(['fixed_with' => $id, 'fixed_at' => Carbon::now()])->save();
        });

        return ['id' => $id, 'applied' => true, ...$preview->toArray()];
    }
}
