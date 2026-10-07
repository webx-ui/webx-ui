<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Throwable;
use WebxUi\Media\Models\MediaFile;

/**
 * Finds files in use by reading the schema rather than by asking each module.
 *
 * A site keeps a library file in two ways, and both can be found without knowing the module:
 *
 * - a foreign key into `media_files` (a cover, a logo, a property image) — the schema says which
 *   columns those are, and they are asked for the ids;
 * - the file's key inside a value (a `wx-media` field `{ path, alt }`, a gallery, rich text, a
 *   setting, a redirect to `/storage/…`) — every text and JSON column is searched for the last
 *   segment of the key. That segment is a uuid, so a match is never a coincidence; and it has no
 *   slash in it, so the escaped slashes `json_encode` writes (`media\/9f\/…`) do not hide it.
 *
 * Bounded: one query per table and per twenty files, at most fifty rows each. Tables that keep
 * history, logs, queues or the library itself are skipped (`webx-media.usage.ignore`) — a file an
 * old version mentioned is not a file in use. A table that cannot be read is reported and passed
 * over rather than failing the whole question.
 */
final class DatabaseUsage implements UsageSource
{
    private const TEXT_TYPES = [
        'char', 'varchar', 'nchar', 'nvarchar', 'character', 'character varying',
        'tinytext', 'text', 'mediumtext', 'longtext', 'ntext', 'clob', 'json', 'jsonb',
    ];

    private const NAMES_PER_QUERY = 20;

    private const ROWS_PER_QUERY = 50;

    public function __construct(private readonly Config $config) {}

    public function find(Collection $files): iterable
    {
        $model = new MediaFile;
        $connection = $model->getConnection();
        $schema = $connection->getSchemaBuilder();
        $prefix = $connection->getTablePrefix();

        /** @var list<int> $ids */
        $ids = array_map('intval', $files->modelKeys());

        /** @var array<int, string> $names file id → the last segment of its key */
        $names = [];

        foreach ($files as $file) {
            $name = basename((string) $file->path);

            if ($name !== '' && $name !== '.') {
                $names[(int) $file->getKey()] = $name;
            }
        }

        foreach ($schema->getTables($schema->getCurrentSchemaName()) as $table) {
            $name = (string) $table['name'];
            $bare = $prefix !== '' && str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;

            if ($this->ignored($bare)) {
                continue;
            }

            try {
                $columns = $schema->getColumns($bare);
                $hasId = in_array('id', array_column($columns, 'name'), true);

                foreach ($schema->getForeignKeys($bare) as $key) {
                    $target = (string) $key['foreign_table'];

                    if (count($key['columns']) !== 1 || ($target !== $model->getTable() && $target !== $prefix.$model->getTable())) {
                        continue;
                    }

                    $column = (string) $key['columns'][0];
                    $rows = $connection->table($bare)
                        ->whereIn($column, $ids)
                        ->limit(self::ROWS_PER_QUERY)
                        ->get($hasId ? ['id', $column] : [$column]);

                    foreach ($rows as $row) {
                        yield new Place((int) $row->{$column}, $bare, $column, $hasId ? $row->id : null);
                    }
                }

                $texts = array_values(array_map(
                    static fn (array $column): string => (string) $column['name'],
                    array_filter($columns, static fn (array $column): bool => in_array(strtolower((string) $column['type_name']), self::TEXT_TYPES, true)),
                ));

                if ($texts === [] || $names === []) {
                    continue;
                }

                foreach (array_chunk($names, self::NAMES_PER_QUERY, true) as $chunk) {
                    $rows = $connection->table($bare)
                        ->where(fn (Builder $query) => $this->anyContains($connection, $query, $texts, $chunk))
                        ->limit(self::ROWS_PER_QUERY)
                        ->get($hasId ? ['id', ...$texts] : $texts);

                    foreach ($rows as $row) {
                        foreach ($texts as $column) {
                            $value = $row->{$column};

                            if (! is_string($value)) {
                                continue;
                            }

                            foreach ($chunk as $fileId => $needle) {
                                if (str_contains($value, $needle)) {
                                    yield new Place($fileId, $bare, $column, $hasId ? $row->id : null);
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $error) {
                report($error);
            }
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  array<int, string>  $needles
     */
    private function anyContains(Connection $connection, Builder $query, array $columns, array $needles): void
    {
        // PostgreSQL has no LIKE on json; everywhere else a text column takes it as it is.
        $cast = $connection->getDriverName() === 'pgsql';

        foreach ($columns as $column) {
            foreach ($needles as $needle) {
                if ($cast) {
                    $query->orWhereRaw($query->getGrammar()->wrap($column).'::text like ?', ['%'.$needle.'%']);
                } else {
                    $query->orWhere($column, 'like', '%'.$needle.'%');
                }
            }
        }
    }

    private function ignored(string $table): bool
    {
        /** @var list<string> $patterns */
        $patterns = (array) $this->config->get('webx-media.usage.ignore', []);

        return Str::is($patterns, $table);
    }
}
