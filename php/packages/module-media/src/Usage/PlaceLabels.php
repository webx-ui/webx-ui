<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

use Illuminate\Database\Connection;
use Throwable;
use WebxUi\Media\Models\MediaFile;

/**
 * A row that uses a file, said the way an editor knows it: by its title, its name or its key —
 * «About us» rather than `pages #12`.
 *
 * Read from the row itself, so it knows no module: the first of a few usual columns the table
 * has. A localised title (`{"en": …, "ru": …}`) is read in the panel's language, or else in the
 * first one it has. A row with none of them, or a table that cannot be read, has no label, and
 * the table and the id are what is said instead.
 */
final class PlaceLabels
{
    /** In order of preference. */
    private const COLUMNS = ['title', 'name', 'label', 'question', 'heading', 'key', 'slug', 'path', 'from'];

    private const LENGTH = 80;

    /** @var array<string, string|null> table → the column read from it */
    private array $columns = [];

    public function of(string $table, int|string|null $id): ?string
    {
        if ($id === null) {
            return null;
        }

        try {
            $connection = (new MediaFile)->getConnection();
            $column = $this->column($connection, $table);

            if ($column === null) {
                return null;
            }

            $value = $connection->table($table)->where('id', $id)->value($column);
        } catch (Throwable) {
            return null;
        }

        return $this->readable($value);
    }

    private function column(Connection $connection, string $table): ?string
    {
        if (! array_key_exists($table, $this->columns)) {
            $names = $connection->getSchemaBuilder()->getColumnListing($table);
            $this->columns[$table] = array_values(array_intersect(self::COLUMNS, $names))[0] ?? null;
        }

        return $this->columns[$table];
    }

    private function readable(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = str_starts_with(ltrim($value), '{') ? json_decode($value, true) : null;

        if (is_array($decoded)) {
            $value = $decoded[app()->getLocale()] ?? null;

            if (! is_string($value) || $value === '') {
                $value = array_values(array_filter($decoded, static fn (mixed $one): bool => is_string($one) && $one !== ''))[0] ?? null;
            }

            if (! is_string($value)) {
                return null;
            }
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_strimwidth($value, 0, self::LENGTH, '…');
    }
}
