<?php

declare(strict_types=1);

namespace WebxUi\Audit\Content;

use WebxUi\Audit\Contracts\AuditContentSource;

/**
 * The modules whose fields the database stage searches, by id. A singleton: content modules
 * register into it from their providers, behind `class_exists`, so none of them needs the audit
 * installed (§7).
 */
final class AuditContentSources
{
    /** @var array<string, AuditContentSource> */
    private array $sources = [];

    public function register(AuditContentSource $source): void
    {
        $this->sources[$source->id()] = $source;
    }

    public function get(string $id): ?AuditContentSource
    {
        return $this->sources[$id] ?? null;
    }

    /** @return array<string, AuditContentSource> */
    public function all(): array
    {
        ksort($this->sources);

        return $this->sources;
    }
}
