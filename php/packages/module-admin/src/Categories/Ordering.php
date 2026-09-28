<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * A list somebody dragged into a new order — one code for the panel and for an agent.
 *
 * Without a category it writes `position` of the rows: the order of categories, or the order of
 * records in the whole list. With one it writes `item_position` in the link table: the order of
 * the records inside that category, which is what an editor drags with that filter on (§2.5 of
 * the services spec).
 *
 * The ids are the list as somebody sees it, not necessarily all of it: a filtered view, a second
 * panel that has not seen the newest row, an agent that named two. So the rows named take the
 * places they hold now, in the new order, and every row not named stays exactly where it is.
 * Numbering the named ones from the top instead would slip unnamed rows in between them — `[4, 2]`
 * out of four once came back as 1, 4, 2, 3. Ids that are not in the list are skipped. One
 * transaction.
 */
final class Ordering
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids  The new order.
     */
    public static function move(string $model, array $ids, ?int $category = null): void
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        $instance = new $model;

        if ($category === null) {
            $key = $instance->getKeyName();
            // Soft-deleted rows included: they keep their place and come back to it.
            $rows = $instance->newQuery()->withoutGlobalScope(SoftDeletingScope::class)->toBase();

            $instance->getConnection()->transaction(static function () use ($rows, $key, $ids): void {
                self::shuffle($rows, $key, 'position', $ids);
            });

            return;
        }

        if (! method_exists($instance, 'categoryLinks')) {
            throw new InvalidArgumentException($model.' is not filed under categories.');
        }

        $relation = $instance->categoryLinks();
        $pivot = $relation->newPivotStatement()->where($relation->getRelatedPivotKeyName(), $category);
        $foreign = $relation->getForeignPivotKeyName();

        $instance->getConnection()->transaction(static function () use ($pivot, $foreign, $ids): void {
            self::shuffle($pivot, $foreign, 'item_position', $ids);
        });
    }

    /**
     * @param  list<int>  $ids
     */
    private static function shuffle(Builder $rows, string $key, string $column, array $ids): void
    {
        /** @var array<int, int> $all  id => place, in the order the list shows them */
        $all = $rows->clone()
            ->orderBy($column)
            ->orderBy($key)
            ->pluck($column, $key)
            ->mapWithKeys(static fn (mixed $at, mixed $id): array => [(int) $id => (int) $at])
            ->all();

        // Places shared by two rows (a list nobody dragged yet) are made a run first, in the
        // order the list shows them: handing a shared place out twice would tie them again.
        if (count(array_unique($all)) !== count($all)) {
            $place = 0;

            foreach (array_keys($all) as $id) {
                if ($all[$id] !== $place) {
                    $rows->clone()->where($key, $id)->update([$column => $place]);
                }

                $all[$id] = $place++;
            }
        }

        $named = array_values(array_filter($ids, static fn (int $id): bool => array_key_exists($id, $all)));
        $slots = array_map(static fn (int $id): int => $all[$id], $named);
        sort($slots);

        foreach ($named as $n => $id) {
            if ($all[$id] !== $slots[$n]) {
                $rows->clone()->where($key, $id)->update([$column => $slots[$n]]);
            }
        }
    }
}
