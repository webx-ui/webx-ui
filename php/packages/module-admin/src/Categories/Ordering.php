<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A list somebody dragged into a new order — one code for the panel and for an agent.
 *
 * Without a category it writes `position` of the rows: the order of categories, or the order of
 * records in the whole list. With one it writes `item_position` in the link table: the order of
 * the records inside that category, which is what an editor drags with that filter on (§2.5 of
 * the services spec).
 *
 * The whole list every time, one update per row inside a transaction. Rows the caller did not
 * name stay where they are rather than being pushed to the end: a second panel open on the same
 * list would otherwise have everything it could not see reordered behind its back. And the ids
 * are checked against the table, so a reorder only moves rows that are there.
 */
final class Ordering
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids  The new order.
     */
    public static function move(string $model, array $ids, ?int $category = null): void
    {
        $ids = array_values(array_map(intval(...), $ids));
        $instance = new $model;

        if ($category === null) {
            $query = $instance->newQuery();
            /** @var list<int> $allowed */
            $allowed = $query->clone()->whereKey($ids)->pluck($instance->getKeyName())->map(intval(...))->all();

            $instance->getConnection()->transaction(static function () use ($query, $ids, $allowed): void {
                foreach ($ids as $position => $id) {
                    if (in_array($id, $allowed, true)) {
                        $query->clone()->whereKey($id)->update(['position' => $position]);
                    }
                }
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
            foreach ($ids as $position => $id) {
                $pivot->clone()->where($foreign, $id)->update(['item_position' => $position]);
            }
        });
    }
}
