<?php

declare(strict_types=1);

namespace WebxUi\Admin\Relations;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Contracts\HasPermissions;

/**
 * What records on this site can be related to, by the key the rows name them with (§3.3 of the
 * recipes spec).
 *
 * A singleton by the pattern of {@see CollectionSources}: a module registers from its provider —
 * not from its route file, which a cached route table never runs — and everything reads on use.
 *
 * Registering is also what cleans up after the target. A service deleted for good takes every
 * row pointing at it along, and the services module writes nothing for that: the listener is put
 * on its model here. Soft deletes leave the rows alone — a service back from the bin comes back
 * with its recipes.
 */
final class RelationTargets
{
    /** @var array<string, RelationTarget> */
    private array $targets = [];

    public function register(RelationTarget $target): void
    {
        $this->targets[$target->key] = $target;

        $model = $target->model;
        $key = $target->key;
        $forget = static function (Model $record) use ($key): void {
            Relations::rows($record)->where('target_type', $key)->where('target_id', $record->getKey())->delete();
        };

        // A model without soft deletes is gone on `deleted`; one with them only on `forceDeleted`.
        // Through the dispatcher by the event's name, which is all `Model::forceDeleted()` does —
        // and which a model not using soft deletes does not have.
        $event = in_array(SoftDeletes::class, class_uses_recursive($model), true) ? 'forceDeleted' : 'deleted';

        Container::getInstance()->make(Dispatcher::class)->listen("eloquent.{$event}: {$model}", $forget);
    }

    public function find(string $key): ?RelationTarget
    {
        return $this->targets[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->targets[$key]);
    }

    /**
     * The key a record is known by here, or null for a record nobody registered — which is what a
     * block asks of the entity whose page it is on.
     */
    public function keyOf(object $record): ?string
    {
        foreach ($this->targets as $key => $target) {
            if ($record instanceof $target->model) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_keys($this->targets);
        sort($keys);

        return $keys;
    }

    /** Whether this administrator may look for candidates: a super administrator carries no permissions and passes. */
    public function allows(RelationTarget $target, ?object $user): bool
    {
        if ($target->permission === null) {
            return true;
        }

        return $user instanceof HasPermissions && $user->hasPermission($target->permission);
    }

    public function forget(): void
    {
        $this->targets = [];
    }
}
