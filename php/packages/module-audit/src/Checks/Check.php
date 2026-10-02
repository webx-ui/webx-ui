<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

use WebxUi\Audit\Contracts\AuditCheck;

/**
 * The bookkeeping every built-in check shares: its id, group, worst severity and needs as
 * constants, and a short way to write a finding. A module's own checks implement the contract
 * directly or extend this — either works.
 */
abstract class Check implements AuditCheck
{
    protected const ID = '';

    protected const GROUP = '';

    protected const SEVERITY = Severity::WARNING;

    /** @var list<string> */
    protected const NEEDS = ['probes'];

    public function id(): string
    {
        return static::ID;
    }

    public function group(): string
    {
        return static::GROUP;
    }

    public function severity(): string
    {
        return static::SEVERITY;
    }

    /** @return list<string> */
    public function needs(): array
    {
        return static::NEEDS;
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @param  array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>}|null  $table
     */
    protected function found(
        string $summary,
        array $params = [],
        ?string $url = null,
        ?string $severity = null,
        ?array $table = null,
        string $key = '',
    ): Finding {
        $details = ['summary' => Finding::summary($summary, $params)];

        if ($table !== null) {
            $details['table'] = $table;
        }

        return new Finding(static::ID, $severity ?? static::SEVERITY, $url, $details, $key);
    }
}
