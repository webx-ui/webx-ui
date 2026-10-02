<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

use WebxUi\Audit\Contracts\AuditCheck;

/**
 * The base for a check another module brings (§7): the same constants as the audit's own,
 * plus the dictionary its texts live in — `<NAMESPACE>::checks.<id>` for the three texts and
 * `<NAMESPACE>::audit.<summary>` for the summary line — so the module ships its words with its
 * check and the audit never has to know them.
 *
 * Extending it is a convenience, not a rule: a module can implement {@see AuditCheck}
 * directly and name its namespace with `textNamespace()`.
 */
abstract class ModuleCheck extends Check
{
    /** `webx-menu` */
    protected const NAMESPACE = '';

    public function textNamespace(): string
    {
        return static::NAMESPACE;
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
        $details = ['summary' => ['key' => static::NAMESPACE.'::audit.'.$summary, 'params' => $params]];

        if ($table !== null) {
            $details['table'] = $table;
        }

        return new Finding(static::ID, $severity ?? static::SEVERITY, $url, $details, $key);
    }
}
