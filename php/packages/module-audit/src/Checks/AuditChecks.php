<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

use WebxUi\Audit\Contracts\AuditCheck;

/**
 * Every check the audit knows, by id (decision 3). A singleton: the module registers its own,
 * and any other module adds its checks from its provider when the audit is installed.
 */
final class AuditChecks
{
    /** @var array<string, AuditCheck> */
    private array $checks = [];

    public function register(AuditCheck $check): void
    {
        $this->checks[$check->id()] = $check;
    }

    public function get(string $id): ?AuditCheck
    {
        return $this->checks[$id] ?? null;
    }

    /** @return array<string, AuditCheck> */
    public function all(): array
    {
        return $this->checks;
    }

    /**
     * The checks a stage can run: everything they need has been collected by now.
     *
     * @param  list<string>  $collected
     * @return list<AuditCheck>
     */
    public function runnable(array $collected, string $stage): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (AuditCheck $check): bool => in_array($stage, $check->needs(), true)
                && array_diff($check->needs(), $collected) === [],
        ));
    }
}
