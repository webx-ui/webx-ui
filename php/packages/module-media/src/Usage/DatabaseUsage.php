<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
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
final class DatabaseUsage implements UsageRewriter, UsageSource
{
    private const TEXT_TYPES = [
        'char', 'varchar', 'nchar', 'nvarchar', 'character', 'character varying',
        'tinytext', 'text', 'mediumtext', 'longtext', 'ntext', 'clob', 'json', 'jsonb',
    ];

    private const NAMES_PER_QUERY = 20;

    private const ROWS_PER_QUERY = 50;

    /** The models of history, named by class so a renamed table follows them. */
    private const TRAILS = [
        'WebxUi\Admin\Versions\EntityVersion',
        'WebxUi\Admin\History\HistoryEntry',
        'WebxUi\Admin\Uploads\Upload',
        'WebxUi\Admin\Notes\Note',
        'WebxUi\Auth\Models\LoginRecord',
        'WebxUi\Blocks\Models\BlockVersion',
        'WebxUi\Mcp\Calls\Call',
    ];

    /** @var list<string>|null */
    private ?array $trails = null;

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

        $tables = $schema->getTables($schema->getCurrentSchemaName());
        $keys = $this->keysIntoLibrary($connection, $model->getTable(), $prefix);

        foreach ($tables as $table) {
            $name = (string) $table['name'];
            $bare = $prefix !== '' && str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;

            if ($this->ignored($bare, 'webx-media.usage.ignore')) {
                continue;
            }

            try {
                $columns = $schema->getColumns($bare);
                $hasId = in_array('id', array_column($columns, 'name'), true);

                foreach ($keys === null ? $this->keysOf($schema, $bare, $model->getTable(), $prefix) : ($keys[$name] ?? []) as $column) {
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
     * Every text and JSON column of every table but the ones `usage.rewrite_ignore` names, with
     * no limit: one `UPDATE … SET column = REPLACE(column, old, new) WHERE column LIKE %old%` per
     * column and file, so a row is never read into PHP and nothing is missed.
     *
     * History is rewritten as well (versions, the journal): restoring a version must not bring
     * back a key whose bytes are gone. Failing is loud on purpose — the caller's transaction
     * undoes the whole file rather than leave half of the site on the old key.
     */
    public function rewrite(array $renames, bool $dryRun = false): array
    {
        $connection = (new MediaFile)->getConnection();
        $schema = $connection->getSchemaBuilder();
        $prefix = $connection->getTablePrefix();
        $counts = [];

        foreach ($renames as [$from, $to]) {
            // Spliced into SQL as literals below, so held to what a key's basename is.
            if (preg_match('/^[A-Za-z0-9._-]+$/', $from.$to) !== 1) {
                throw new \InvalidArgumentException("[{$from}] → [{$to}] is not a rename of a library key.");
            }
        }

        foreach ($schema->getTables($schema->getCurrentSchemaName()) as $table) {
            $name = (string) $table['name'];
            $bare = $prefix !== '' && str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;

            if ($this->ignored($bare, 'webx-media.usage.rewrite_ignore')) {
                continue;
            }

            foreach ($schema->getColumns($bare) as $column) {
                $type = strtolower((string) $column['type_name']);

                if (! in_array($type, self::TEXT_TYPES, true)) {
                    continue;
                }

                $column = (string) $column['name'];

                foreach ($renames as $fileId => [$from, $to]) {
                    $query = $connection->table($bare);
                    $this->contains($connection, $query, $column, $type, $from);

                    $count = $dryRun
                        ? $query->count()
                        : $query->update([$column => $connection->raw($this->replaced($connection, $column, $type, $from, $to))]);

                    if ($count > 0) {
                        $counts[$fileId] = ($counts[$fileId] ?? 0) + $count;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * Every single-column foreign key into the library, by table, in one question to
     * `information_schema` — where the database has one to ask. Asked table by table instead,
     * MariaDB took two and a half seconds over eighty tables, and the panel asks before every
     * delete. `null` means: ask each table ({@see keysOf()}).
     *
     * @return array<string, list<string>>|null
     */
    private function keysIntoLibrary(Connection $connection, string $library, string $prefix): ?array
    {
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        try {
            $rows = $connection->select(
                'select TABLE_NAME as table_name, COLUMN_NAME as column_name, CONSTRAINT_NAME as name
                 from information_schema.KEY_COLUMN_USAGE
                 where TABLE_SCHEMA = database() and REFERENCED_TABLE_NAME = ?',
                [$prefix.$library],
            );
        } catch (Throwable $error) {
            report($error);

            return null;
        }

        /** @var array<string, array<string, list<string>>> $constraints table → constraint → columns */
        $constraints = [];

        foreach ($rows as $row) {
            $constraints[(string) $row->table_name][(string) $row->name][] = (string) $row->column_name;
        }

        $keys = [];

        foreach ($constraints as $table => $named) {
            foreach ($named as $columns) {
                if (count($columns) === 1) {
                    $keys[$table][] = $columns[0];
                }
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function keysOf(SchemaBuilder $schema, string $table, string $library, string $prefix): array
    {
        $columns = [];

        foreach ($schema->getForeignKeys($table) as $key) {
            $target = (string) $key['foreign_table'];

            if (count($key['columns']) === 1 && ($target === $library || $target === $prefix.$library)) {
                $columns[] = (string) $key['columns'][0];
            }
        }

        return $columns;
    }

    private function contains(Connection $connection, Builder $query, string $column, string $type, string $needle): void
    {
        if ($connection->getDriverName() === 'pgsql' && in_array($type, ['json', 'jsonb'], true)) {
            $query->whereRaw($query->getGrammar()->wrap($column).'::text like ?', ['%'.$needle.'%']);

            return;
        }

        $query->where($column, 'like', '%'.$needle.'%');
    }

    private function replaced(Connection $connection, string $column, string $type, string $from, string $to): string
    {
        $wrapped = $connection->getQueryGrammar()->wrap($column);
        $replace = "replace(%s, {$connection->escape($from)}, {$connection->escape($to)})";

        if ($connection->getDriverName() === 'pgsql' && in_array($type, ['json', 'jsonb'], true)) {
            return sprintf($replace, $wrapped.'::text').'::'.$type;
        }

        return sprintf($replace, $wrapped);
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

    private function ignored(string $table, string $key): bool
    {
        /** @var list<string> $patterns */
        $patterns = (array) $this->config->get($key, []);

        if ($key === 'webx-media.usage.ignore') {
            $patterns = [...$patterns, ...$this->trails()];
        }

        return Str::is($patterns, $table);
    }

    /**
     * The tables of the panel's own trails — versions, the journal, uploads in progress, notes,
     * sign-ins, agents' calls — as their models name them. A mention there is the past, not a
     * use: eight old versions of a page listed before the page itself crowded the page off the
     * list ({@see MediaUsage::LIMIT}), and the tables had been renamed (`entity_versions` →
     * `cms_versions`) under a config that still named them the old way.
     *
     * A rewrite of keys does not ask this: history is rewritten on purpose.
     *
     * @return list<string>
     */
    private function trails(): array
    {
        if ($this->trails !== null) {
            return $this->trails;
        }

        $tables = [];

        foreach (self::TRAILS as $class) {
            if (class_exists($class)) {
                $tables[] = (new $class)->getTable();
            }
        }

        return $this->trails = $tables;
    }
}
