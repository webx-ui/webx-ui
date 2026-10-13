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
    /** @var array<string, array{permissions: list<string>, write: list<string>, find: Closure(string): ?array{revision: string, model: Model}, place: (Closure(Model): array<string, mixed>)|null, model: class-string<Model>|null}> */
    private array $records = [];

    /**
     * @param  string|list<string>  $permission  Any of them lets a reader in.
     * @param  Closure(string): (array{revision: string, model: Model}|null)  $find  A record in the bin too, for a model that has one:
     *                                                                               the editor open on it has to hear that it went there.
     * @param  string|list<string>|null  $write  What putting an old draft back takes; the read permission when left out.
     * @param  (Closure(Model): array<string, mixed>)|null  $place  Where the record sits, for one that can be moved — a page's
     *                                                              parent and address. An open editor compares it with what it
     *                                                              shows and catches up when it moved.
     * @param  class-string<Model>|null  $model  The model whose key the id is. With it, an editor open on a record somebody
     *                                           deleted for good hears who did it and when, rather than nothing.
     */
    public function register(string $entity, string|array $permission, Closure $find, string|array|null $write = null, ?Closure $place = null, ?string $model = null): void
    {
        $read = is_array($permission) ? array_values($permission) : [$permission];

        $this->records[$entity] = [
            'permissions' => $read,
            'write' => $write === null ? $read : (is_array($write) ? array_values($write) : [$write]),
            'find' => $find,
            'place' => $place,
            'model' => $model,
        ];
    }

    /** @return class-string<Model>|null */
    public function model(string $entity): ?string
    {
        return $this->records[$entity]['model'] ?? null;
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

    /**
     * The revision of a record that is registered here, found from the model — for a tool of
     * another module that writes into it. `blocks_edit_content` on a page answered with the hash
     * of the block tree while `pages_get` answered with the page's, and an agent that read with
     * one and wrote with the other was told the page had changed when nothing had. A record has
     * one revision, the one its editor holds; `null` for a model nobody registered.
     */
    public function revisionOf(Model $model): ?string
    {
        return $this->recordOf($model)['revision'] ?? null;
    }

    /**
     * The name a record is registered under — what its editor's heartbeat calls it: `pages` for
     * a page. The panel on the site knows a record by the type of its address (`page`) and needs
     * this one to say «I have it open» in the same place the editor in the panel listens.
     */
    public function nameOf(Model $model): ?string
    {
        return $this->recordOf($model)['entity'] ?? null;
    }

    /**
     * @return array{entity: string, revision: string}|null
     */
    private function recordOf(Model $model): ?array
    {
        foreach (array_keys($this->records) as $entity) {
            // Only a record whose model is this one's: a region is found by name, and asking it
            // for «12» is a question about a different thing.
            $class = $this->records[$entity]['model'];

            if ($class !== null && ! $model instanceof $class) {
                continue;
            }

            $found = $this->find($entity, (string) $model->getKey());

            if ($found !== null && $model::class === $found['model']::class && $found['model']->getKey() === $model->getKey()) {
                return ['entity' => $entity, 'revision' => $found['revision']];
            }
        }

        return null;
    }

    /**
     * Where the record sits, when its module said how to tell.
     *
     * @return array<string, mixed>|null
     */
    public function place(string $entity, Model $model): ?array
    {
        $place = $this->records[$entity]['place'] ?? null;

        return $place === null ? null : $place($model);
    }
}
