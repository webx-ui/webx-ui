<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * The records an editor of the panel keeps watch over while it is open.
 *
 * A module registers its drafted records here by the name its editor uses — `pages`,
 * `services`, `regions` — with the permission that reads them and a way to find one with its
 * current revision. The editor then asks one address every few seconds (`editing/{entity}/{id}`)
 * and hears whether the record moved under it, who moved it and through which door, and who
 * else has it open — without each section growing an endpoint of its own for it.
 */
final class EditedRecords
{
    /** @var array<string, array{permissions: list<string>, write: list<string>, find: Closure(string): ?array{revision: string, model: Model}}> */
    private array $records = [];

    /**
     * @param  string|list<string>  $permission  Any of them lets a reader in.
     * @param  Closure(string): (array{revision: string, model: Model}|null)  $find
     * @param  string|list<string>|null  $write  What putting an old draft back takes; the read permission when left out.
     */
    public function register(string $entity, string|array $permission, Closure $find, string|array|null $write = null): void
    {
        $read = is_array($permission) ? array_values($permission) : [$permission];

        $this->records[$entity] = [
            'permissions' => $read,
            'write' => $write === null ? $read : (is_array($write) ? array_values($write) : [$write]),
            'find' => $find,
        ];
    }

    public function has(string $entity): bool
    {
        return isset($this->records[$entity]);
    }

    /** @return list<string> */
    public function permissions(string $entity): array
    {
        return $this->records[$entity]['permissions'] ?? [];
    }

    /** @return list<string> */
    public function writePermissions(string $entity): array
    {
        return $this->records[$entity]['write'] ?? [];
    }

    /**
     * @return array{revision: string, model: Model}|null
     */
    public function find(string $entity, string $id): ?array
    {
        $record = $this->records[$entity] ?? null;

        return $record === null ? null : ($record['find'])($id);
    }
}
