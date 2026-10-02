<?php

declare(strict_types=1);

namespace WebxUi\Audit\Fixes;

use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Contracts\AuditFix;

/**
 * Every fix the audit can offer, by id (decision 4). A singleton, like the checks: the module
 * that owns what a fix changes registers it from its own provider when the audit is installed.
 */
final class AuditFixes
{
    /** @var array<string, AuditFix> */
    private array $fixes = [];

    public function register(AuditFix $fix): void
    {
        $this->fixes[$fix->id()] = $fix;
    }

    public function get(string $id): ?AuditFix
    {
        return $this->fixes[$id] ?? null;
    }

    /** @return array<string, AuditFix> */
    public function all(): array
    {
        return $this->fixes;
    }

    /**
     * The ids of the fixes that close a check — whether the findings screen offers a button.
     *
     * @return list<string>
     */
    public function forCheck(string $check): array
    {
        return array_values(array_map(
            static fn (AuditFix $fix): string => $fix->id(),
            array_filter($this->fixes, static fn (AuditFix $fix): bool => in_array($check, $fix->fixes(), true)),
        ));
    }

    /**
     * The fixes that can close this very finding now.
     *
     * @return list<AuditFix>
     */
    public function available(Finding $finding): array
    {
        return array_values(array_filter(
            $this->fixes,
            static fn (AuditFix $fix): bool => in_array($finding->check, $fix->fixes(), true) && $fix->available($finding),
        ));
    }
}
