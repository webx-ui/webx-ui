<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Auth\Models\CmsUser;

/**
 * Names for the `author_id` of versions, in one query per response rather than one per row.
 * The history is an audit log, and a log of numbers is not one anybody reads.
 */
final class Authors
{
    /**
     * @param  iterable<array-key, int|null>  $ids
     * @return array<int, string>
     */
    public static function names(iterable $ids): array
    {
        $wanted = [];

        foreach ($ids as $id) {
            if (is_int($id)) {
                $wanted[$id] = true;
            }
        }

        if ($wanted === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = CmsUser::query()
            ->whereIn('id', array_keys($wanted))
            ->pluck('name', 'id')
            ->all();

        return $names;
    }
}
