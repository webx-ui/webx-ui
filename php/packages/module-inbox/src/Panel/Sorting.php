<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A list somebody dragged into a new order.
 *
 * One update per row, inside a transaction. Rows the caller did not name are left where they
 * are rather than pushed to the end: a second screen open on the same list would otherwise
 * have everything it could not see reordered behind its back. And the ids are checked against
 * the query they came with — that is what stops a reorder of one form's fields from moving
 * another form's.
 */
final class Sorting
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  The rows that may be reordered.
     * @param  list<int>  $ids  Their new order.
     */
    public static function apply(Builder $query, array $ids): void
    {
        /** @var list<int> $allowed */
        $allowed = $query->clone()->whereIn('id', $ids)->pluck('id')->map(intval(...))->all();

        DB::transaction(function () use ($query, $ids, $allowed): void {
            foreach ($ids as $position => $id) {
                if (! in_array($id, $allowed, true)) {
                    continue;
                }

                $query->clone()->whereKey($id)->update(['position' => $position]);
            }
        });
    }
}
