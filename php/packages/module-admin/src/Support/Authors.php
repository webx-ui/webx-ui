<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Names for a column of administrator ids — the author of a note, of a log line, of a
 * version — in one query rather than one per row.
 *
 * This package does not know which table administrators live in — it has no dependency on an
 * auth module and must keep working without one — so the query is made through the reader's
 * own model. Whoever is reading a panel is an administrator of exactly the kind that wrote the
 * rows they are reading, and their model is therefore the right table to ask.
 *
 * A reader who is not an Eloquent model at all (a `GenericUser`, a token identity) can still
 * be named: they know their own name, and that covers the case the panel actually shows.
 */
final class Authors
{
    /**
     * @param  iterable<array-key, int|null>  $ids
     * @return array<int, string>
     */
    public static function names(mixed $reader, iterable $ids): array
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

        if ($reader instanceof Model) {
            /** @var array<int, string> $names */
            $names = $reader->newQuery()
                ->whereIn($reader->getKeyName(), array_keys($wanted))
                ->pluck('name', $reader->getKeyName())
                ->all();

            return $names;
        }

        $id = is_object($reader) && method_exists($reader, 'getAuthIdentifier')
            ? $reader->getAuthIdentifier()
            : null;

        $name = is_object($reader) && property_exists($reader, 'name') ? (string) $reader->name : '';

        return is_numeric($id) && isset($wanted[(int) $id]) && $name !== ''
            ? [(int) $id => $name]
            : [];
    }
}
