<?php

declare(strict_types=1);

namespace WebxUi\Admin\Relations;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * The questions about relations that are asked of many records at once, or from the other end.
 *
 *     Relations::load($recipes, 'services');              // one query per role and target type
 *     Relations::owners('recipe', 'services', $service);  // the recipes of this service
 *
 * The rows are read on the connection of the record asked about, never on the default one: a
 * model on another connection would otherwise join halves with different table prefixes
 * (CLAUDE.md §4).
 */
final class Relations
{
    public const TABLE = 'webx_relations';

    /**
     * The key a record's draft keeps its relations under, role by role — here rather than on
     * {@see HasRelations}: a trait's constant cannot be read from outside it (CLAUDE.md §4).
     */
    public const DRAFT = 'relations';

    /**
     * Load one role of many owners in one go — the list of cards, not a query per card.
     *
     * @param  iterable<Model>  $records  Owners of one class, using {@see HasRelations}.
     */
    public static function load(iterable $records, string $role): void
    {
        $byId = [];

        foreach ($records as $record) {
            $byId[(int) $record->getKey()] = $record;
        }

        if ($byId === []) {
            return;
        }

        $first = reset($byId);
        $owner = self::ownerKey($first);

        $rows = self::rows($first)
            ->where('owner_type', $owner)
            ->whereIn('owner_id', array_keys($byId))
            ->where('role', $role)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['owner_id', 'target_type', 'target_id']);

        $wanted = [];

        foreach ($rows as $row) {
            $wanted[(string) $row->target_type][(int) $row->target_id] = true;
        }

        $found = self::find($wanted);
        $lists = array_fill_keys(array_keys($byId), []);

        foreach ($rows as $row) {
            $target = $found[(string) $row->target_type][(int) $row->target_id] ?? null;

            if ($target instanceof Model) {
                $lists[(int) $row->owner_id][] = $target;
            }
        }

        foreach ($byId as $id => $record) {
            if (method_exists($record, 'setLoadedRelated')) {
                $record->setLoadedRelated($role, new Collection($lists[$id]));
            }
        }
    }

    /**
     * The owners related through a role to this record — the recipes of a service. A query, so
     * the caller adds what is theirs: visibility, order, a limit.
     *
     * @param  string  $owner  The owner's key in the registry (`recipe`), or its class.
     * @return Builder<Model>
     */
    public static function owners(string $owner, string $role, Model $target): Builder
    {
        $targets = self::targets();
        $class = class_exists($owner) ? $owner : $targets->find($owner)?->model;

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException("Nothing is registered as [{$owner}].");
        }

        $targetKey = $targets->keyOf($target) ?? throw new InvalidArgumentException($target::class.' is not a registered relation target.');

        /** @var Builder<Model> $query */
        $query = $class::query();

        return $query->scopes(['relatedTo' => [$role, $targetKey, [(int) $target->getKey()]]]);
    }

    /**
     * The rows, on the record's connection.
     */
    public static function rows(Model $record): QueryBuilder
    {
        return $record->getConnection()->table(self::TABLE);
    }

    /**
     * Records by type and id, each type asked once. A type no installed module answers for is
     * skipped: its rows wait for the module to come back.
     *
     * @param  array<string, array<int, true>>  $wanted
     * @return array<string, array<int, Model>>
     */
    public static function find(array $wanted): array
    {
        $targets = self::targets();
        $found = [];

        foreach ($wanted as $type => $ids) {
            $target = $targets->find($type);

            if ($target === null || $ids === []) {
                continue;
            }

            foreach ($target->query()->whereKey(array_keys($ids))->get() as $record) {
                $found[$type][(int) $record->getKey()] = $record;
            }
        }

        return $found;
    }

    public static function targets(): RelationTargets
    {
        return Container::getInstance()->make(RelationTargets::class);
    }

    private static function ownerKey(Model $record): string
    {
        if (! method_exists($record, 'relationKey')) {
            throw new InvalidArgumentException($record::class.' does not use HasRelations.');
        }

        return (string) $record->relationKey();
    }
}
