<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Names (and photographs) for a column of administrator ids — the author of a note, of a log
 * line, of a version — in one query rather than one per row.
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
        return array_map(static fn (array $person): string => $person['name'], self::people($reader, $ids));
    }

    /**
     * Names and photographs, for whatever draws an author as a face — a note, a line of the
     * journal. Still the one query: the photograph is a column of the same row.
     *
     * The photograph is the key it is stored under, not an address: the panel resolves keys
     * through its library, in one batch for the whole feed. Null where the table has no such
     * column or the person never set one — initials are what is drawn then.
     *
     * @param  iterable<array-key, int|null>  $ids
     * @return array<int, array{name: string, avatar: string|null}>
     */
    public static function people(mixed $reader, iterable $ids): array
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
            $people = [];

            foreach ($reader->newQuery()->whereIn($reader->getKeyName(), array_keys($wanted))->get() as $person) {
                // Read off the row rather than through `getAttribute`: a table without the column
                // is a site without photographs, and a model that forbids missing attributes
                // would throw on it.
                $row = $person->getAttributes();
                $avatar = $row['avatar'] ?? null;

                $people[(int) $person->getKey()] = [
                    'name' => (string) ($row['name'] ?? ''),
                    'avatar' => is_string($avatar) && $avatar !== '' ? $avatar : null,
                ];
            }

            return $people;
        }

        $id = is_object($reader) && method_exists($reader, 'getAuthIdentifier')
            ? $reader->getAuthIdentifier()
            : null;

        $name = is_object($reader) && property_exists($reader, 'name') ? (string) $reader->name : '';

        return is_numeric($id) && isset($wanted[(int) $id]) && $name !== ''
            ? [(int) $id => ['name' => $name, 'avatar' => null]]
            : [];
    }
}
