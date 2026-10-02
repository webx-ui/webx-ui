<?php

declare(strict_types=1);

namespace WebxUi\Routing\Audit;

use ArrayObject;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Contracts\AuditCheck;
use WebxUi\Routing\RegistryHealth;

/**
 * What the address registry brings to the site audit (audit spec §7): `webx:routes:check` as
 * findings — an alias that leads nowhere, a canonical row whose entity is gone, an entity with no
 * address, an address the application answers itself. One class, a check per kind of problem;
 * the registry is read once per run, not once per check.
 */
final class RegistryCheck implements AuditCheck
{
    /** kind => [check id, severity] */
    public const KINDS = [
        'orphan' => ['routing.orphan', Severity::ERROR],
        'alias_broken' => ['routing.alias_broken', Severity::ERROR],
        'shadowed' => ['routing.shadowed', Severity::WARNING],
        'no_address' => ['routing.no_address', Severity::WARNING],
        'unknown_type' => ['routing.unknown_type', Severity::WARNING],
    ];

    /**
     * @param  ArrayObject<int, list<array{string, string, string}>>  $memo  run id => problems, shared by the checks of one provider
     */
    public function __construct(
        private readonly string $kind,
        private readonly RegistryHealth $health,
        private readonly ArrayObject $memo,
    ) {}

    public function textNamespace(): string
    {
        return 'webx-routing';
    }

    public function id(): string
    {
        return self::KINDS[$this->kind][0];
    }

    public function group(): string
    {
        return 'routing';
    }

    public function severity(): string
    {
        return self::KINDS[$this->kind][1];
    }

    public function needs(): array
    {
        return ['database'];
    }

    public function run(AuditContext $context): iterable
    {
        $runId = $context->run->id;

        if (! isset($this->memo[$runId])) {
            $this->memo->exchangeArray([$runId => $this->health->problems()]);
        }

        foreach ($this->memo[$runId] as [$kind, , $where]) {
            if ($kind !== $this->kind) {
                continue;
            }

            $path = str_starts_with($where, '/') ? strtok($where, ' ') : false;

            yield new Finding(
                $this->id(),
                $this->severity(),
                is_string($path) ? $context->base().$path : null,
                ['summary' => ['key' => 'webx-routing::audit.'.$this->kind, 'params' => ['where' => $where]]],
                $where,
            );
        }
    }
}
