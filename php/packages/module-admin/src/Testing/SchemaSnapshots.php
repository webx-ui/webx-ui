<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use PDO;

/**
 * The migrated sqlite schemas of this process, for `MigratesOnce`: the statements that made the
 * tables, indexes, views and triggers, in the order sqlite keeps them, and the rows the
 * migrations wrote. Kept in memory, so parallel processes never share one.
 *
 * @internal
 */
final class SchemaSnapshots
{
    /** @var array<string, array{statements: list<string>, rows: array<string, list<array<string, mixed>>>}> */
    private static array $schemas = [];

    /**
     * @return array{statements: list<string>, rows: array<string, list<array<string, mixed>>>}|null
     */
    public static function get(string $key): ?array
    {
        return self::$schemas[$key] ?? null;
    }

    /**
     * @param  array{statements: list<string>, rows: array<string, list<array<string, mixed>>>}  $schema
     */
    public static function put(string $key, array $schema): void
    {
        self::$schemas[$key] = $schema;
    }

    public static function isEmpty(PDO $pdo): bool
    {
        $objects = $pdo->query('select 1 from sqlite_master limit 1');

        return $objects !== false && $objects->fetchColumn() === false;
    }

    /**
     * @return array{statements: list<string>, rows: array<string, list<array<string, mixed>>>}
     */
    public static function take(PDO $pdo): array
    {
        $statements = [];
        $rows = [];

        // `sqlite_*` are sqlite's own: the autoindexes come back with their tables, and
        // `sqlite_sequence` with the first AUTOINCREMENT table — only its rows are copied.
        $objects = $pdo->query("select type, name, sql from sqlite_master where sql is not null and name not like 'sqlite_%' order by rowid");

        foreach ($objects === false ? [] : $objects->fetchAll(PDO::FETCH_ASSOC) as $object) {
            $statements[] = (string) $object['sql'];

            if ($object['type'] === 'table') {
                $rows[(string) $object['name']] = self::rows($pdo, (string) $object['name']);
            }
        }

        $sequence = $pdo->query("select 1 from sqlite_master where name = 'sqlite_sequence'");

        if ($sequence !== false && $sequence->fetchColumn() !== false) {
            $rows['sqlite_sequence'] = self::rows($pdo, 'sqlite_sequence');
        }

        return ['statements' => $statements, 'rows' => array_filter($rows)];
    }

    /**
     * @param  array{statements: list<string>, rows: array<string, list<array<string, mixed>>>}  $schema
     */
    public static function restore(PDO $pdo, array $schema): void
    {
        $foreignKeys = $pdo->query('pragma foreign_keys');
        $enforced = $foreignKeys !== false && (int) $foreignKeys->fetchColumn() === 1;

        // Rows go in the order the tables were made, not the order they reference each other.
        $pdo->exec('pragma foreign_keys = off');
        $pdo->beginTransaction();

        // Triggers after the rows: the rows are already what the triggers made of them.
        $triggers = array_filter($schema['statements'], static fn (string $sql): bool => preg_match('/^create\s+trigger/i', $sql) === 1);

        foreach (array_diff_key($schema['statements'], $triggers) as $statement) {
            $pdo->exec($statement);
        }

        self::insert($pdo, $schema['rows']);

        foreach ($triggers as $statement) {
            $pdo->exec($statement);
        }

        $pdo->commit();

        if ($enforced) {
            $pdo->exec('pragma foreign_keys = on');
        }
    }

    /**
     * The rows of `take()` back into the same schema, instead of whatever its tables hold now:
     * for a test that seeds a lot once and wants it fresh in every test after. Triggers fire on the
     * way; no migration here has one.
     *
     * @param  array<string, list<array<string, mixed>>>  $rows
     */
    public static function refill(PDO $pdo, array $rows): void
    {
        $foreignKeys = $pdo->query('pragma foreign_keys');
        $enforced = $foreignKeys !== false && (int) $foreignKeys->fetchColumn() === 1;

        $pdo->exec('pragma foreign_keys = off');
        $pdo->beginTransaction();

        $tables = $pdo->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'");

        foreach ($tables === false ? [] : $tables->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec(sprintf('delete from "%s"', $table));
        }

        $sequence = $pdo->query("select 1 from sqlite_master where name = 'sqlite_sequence'");

        if ($sequence !== false && $sequence->fetchColumn() !== false) {
            $pdo->exec('delete from sqlite_sequence');
        }

        self::insert($pdo, $rows);

        $pdo->commit();

        if ($enforced) {
            $pdo->exec('pragma foreign_keys = on');
        }
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    private static function insert(PDO $pdo, array $tables): void
    {
        foreach ($tables as $table => $rows) {
            $columns = array_keys($rows[0]);
            $insert = $pdo->prepare(sprintf(
                'insert into "%s" (%s) values (%s)',
                $table,
                implode(', ', array_map(static fn (string $column): string => '"'.$column.'"', $columns)),
                implode(', ', array_fill(0, count($columns), '?')),
            ));

            foreach ($rows as $row) {
                $position = 1;

                foreach ($row as $value) {
                    $insert->bindValue($position++, $value, match (true) {
                        $value === null => PDO::PARAM_NULL,
                        is_int($value) => PDO::PARAM_INT,
                        default => PDO::PARAM_STR,
                    });
                }

                $insert->execute();
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(PDO $pdo, string $table): array
    {
        $statement = $pdo->query(sprintf('select * from "%s"', $table));

        /** @var list<array<string, mixed>> */
        return $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
