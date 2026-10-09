<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;

/**
 * Puts an archive into this stand, one step at a time; the command decides the order and what
 * to ask in between.
 */
final class Restorer
{
    public const DRIVERS = ['mysql', 'mariadb', 'sqlite'];

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SnapshotTables $tables,
        private readonly MediaDisk $media,
    ) {}

    /**
     * The manifest alone. It is the first file of the archive, so this reads a few kilobytes
     * whatever the size of the photographs behind it.
     */
    public function manifest(string $archive): Manifest
    {
        foreach (Tar::read($archive) as $name => $entry) {
            if ($name !== Manifest::NAME) {
                break;
            }

            return Manifest::fromJson($entry->contents(), $archive);
        }

        throw SnapshotFailed::notASnapshot($archive);
    }

    public function supported(): bool
    {
        return in_array($this->connection()->getDriverName(), self::DRIVERS, true);
    }

    public function driver(): string
    {
        return $this->connection()->getDriverName();
    }

    public function plan(Manifest $manifest, bool $all, bool $withAdmins, bool $media, bool $keepExtra): RestorePlan
    {
        $here = Snapshotter::tablesOf($this->connection());
        $archived = $manifest->tables();

        $replace = $kept = $missing = $untouched = $derived = $undeclared = [];
        $rows = 0;

        foreach ($archived as $table => $meta) {
            if (! in_array($table, $here, true)) {
                $missing[] = $table;
            } elseif ($this->tables->travels($table, $all, $withAdmins)) {
                $replace[] = $table;
                $rows += (int) $meta['rows'];
            } else {
                $kept[] = $table;
            }
        }

        foreach ($here as $table) {
            $group = $this->tables->groupOf($table);

            if ($group === TableGroup::Derived) {
                $derived[] = $table;
            } elseif ($group === null) {
                $undeclared[] = $table;
            }

            if (! isset($archived[$table]) && $group !== TableGroup::Derived && $this->tables->travels($table, $all, $withAdmins)) {
                $untouched[] = $table;
            }
        }

        /** @var Migrator $migrator */
        $migrator = Container::getInstance()->make('migrator');
        $known = array_keys($migrator->getMigrationFiles(array_merge(
            [database_path('migrations')],
            $migrator->paths(),
        )));
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $archivedMigrations = $manifest->migrations();

        $restoreMedia = $media && $manifest->hasMedia();
        $deletions = 0;

        if ($restoreMedia && ! $keepExtra) {
            $deletions = count(array_diff($this->media->files(), array_keys($manifest->mediaFiles())));
        }

        return new RestorePlan(
            replace: $replace,
            kept: $kept,
            missing: $missing,
            untouched: $untouched,
            derived: $derived,
            undeclared: $undeclared,
            unknownMigrations: array_values(array_diff($archivedMigrations, $known)),
            pendingMigrations: array_values(array_diff($known, $ran)),
            newerHere: array_values(array_diff($ran, $archivedMigrations)),
            rows: $rows,
            media: $restoreMedia,
            files: $restoreMedia ? count($manifest->mediaFiles()) : 0,
            deletions: $deletions,
        );
    }

    /**
     * Unpacks into `$staging` and checks every file against its hash before anything here is
     * touched: an archive damaged on the way is refused whole, not restored by half.
     */
    public function extract(string $archive, Manifest $manifest, string $staging, bool $media): void
    {
        $expected = $media ? $manifest->mediaFiles() : [];
        $seen = [];
        $database = false;

        foreach (Tar::read($archive) as $name => $entry) {
            if ($entry->directory) {
                continue;
            }

            if ($name === Manifest::DATABASE) {
                $entry->copyTo($staging.'/'.Manifest::DATABASE);
                $database = true;

                continue;
            }

            if (! $media || ! str_starts_with($name, Manifest::MEDIA)) {
                continue;
            }

            $relative = substr($name, strlen(Manifest::MEDIA));

            if (! self::safe($relative)) {
                throw SnapshotFailed::unsafePath($name);
            }

            if (! isset($expected[$relative])) {
                continue;
            }

            $target = $staging.'/'.Manifest::MEDIA.$relative;
            $entry->copyTo($target);

            if (! hash_equals($expected[$relative], (string) hash_file('sha256', $target))) {
                throw SnapshotFailed::corrupted($name);
            }

            $seen[$relative] = true;
        }

        if (! $database) {
            throw SnapshotFailed::missingEntry(Manifest::DATABASE);
        }

        foreach (array_keys($expected) as $relative) {
            if (! isset($seen[$relative])) {
                throw SnapshotFailed::missingEntry(Manifest::MEDIA.$relative);
            }
        }
    }

    /**
     * Replaces the rows of the planned tables in one transaction, with foreign keys off: the
     * groups point at each other (an enquiry at its form), and the archive's order is the
     * alphabet's, not the keys'. What the keys would have said is reported afterwards by
     * `orphans()` instead of failing halfway.
     *
     * Columns are matched by name. A column the archive has and this stand dropped is left out;
     * one this stand added takes its default. Both are what an older archive looks like.
     *
     * @return array{rows: array<string, int>, dropped: array<string, list<string>>, preserved: array<string, int>}
     */
    public function importDatabase(RestorePlan $plan, string $staging, UrlRewriter $rewriter): array
    {
        $connection = $this->connection();
        $schema = $connection->getSchemaBuilder();
        $path = $staging.'/'.Manifest::DATABASE;
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw SnapshotFailed::cannotRead($path);
        }

        $result = ['rows' => [], 'dropped' => [], 'preserved' => []];
        $replace = array_flip($plan->replace);

        $schema->disableForeignKeyConstraints();

        try {
            $connection->transaction(function () use ($connection, $schema, $handle, $plan, $replace, $rewriter, &$result): void {
                foreach ($plan->derived as $table) {
                    $connection->table($table)->delete();
                }

                $table = null;
                $keep = [];
                $preserve = null;
                $batch = [];
                $size = 1;
                // The ids of the rows this stand keeps, and the archive's rows that would collide with
                // them: those go in last, under a new id (nothing points at a setting by its id).
                $taken = [];
                $displaced = [];

                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                    if (! array_is_list($decoded)) {
                        $batch = self::insert($connection, $table, $batch);
                        $displaced = self::insertEach($connection, $table, $displaced);
                        $table = isset($replace[$decoded['table']]) ? (string) $decoded['table'] : null;

                        if ($table === null) {
                            continue;
                        }

                        $columns = array_map(strval(...), $decoded['columns']);
                        $present = array_flip($schema->getColumnListing($table));
                        $keep = array_filter($columns, static fn (string $column): bool => isset($present[$column]));
                        $dropped = array_values(array_diff($columns, $keep));

                        if ($dropped !== []) {
                            $result['dropped'][$table] = $dropped;
                        }

                        $preserve = $this->tables->preservedRows($table);
                        $query = $connection->table($table);

                        if ($preserve !== null) {
                            $query->where(static function ($where) use ($preserve): void {
                                $where->whereNotIn($preserve['column'], $preserve['values'])->orWhereNull($preserve['column']);
                            });
                            $result['preserved'][$table] = count($preserve['values']);
                        }

                        $query->delete();
                        $taken = $preserve !== null && isset($present['id'])
                            ? array_flip(array_map(strval(...), $connection->table($table)->pluck('id')->all()))
                            : [];
                        $result['rows'][$table] = 0;
                        // SQLite counts bound values against a limit of 999 on older builds.
                        $size = max(1, intdiv(900, max(1, count($keep))));

                        continue;
                    }

                    if ($table === null) {
                        continue;
                    }

                    $row = [];

                    foreach ($keep as $index => $column) {
                        $value = $decoded[$index] ?? null;

                        if (is_array($value)) {
                            $value = base64_decode((string) ($value['b64'] ?? ''), true);
                        } elseif (is_string($value)) {
                            $value = $rewriter->rewrite($value);
                        }

                        $row[$column] = $value;
                    }

                    if ($preserve !== null && in_array($row[$preserve['column']] ?? null, $preserve['values'], false)) {
                        continue;
                    }

                    $result['rows'][$table]++;

                    if ($taken !== [] && isset($row['id'], $taken[(string) $row['id']])) {
                        unset($row['id']);
                        $displaced[] = $row;

                        continue;
                    }

                    $batch[] = $row;

                    if (count($batch) >= $size) {
                        $batch = self::insert($connection, $table, $batch);
                    }
                }

                self::insert($connection, $table, $batch);
                self::insertEach($connection, $table, $displaced);
            });
        } finally {
            fclose($handle);
            $schema->enableForeignKeyConstraints();
        }

        return $result;
    }

    /**
     * Rows that point at something no longer there — an enquiry whose form the archive did not
     * have, a page under a parent that went. Counted, not fixed: which side is wrong is a
     * decision for whoever reads the line.
     *
     * @param  list<string>  $replaced
     * @return list<array{table: string, column: string, parent: string, count: int}>
     */
    public function orphans(array $replaced): array
    {
        $connection = $this->connection();
        $schema = $connection->getSchemaBuilder();
        $replaced = array_flip($replaced);
        $found = [];

        foreach (Snapshotter::tablesOf($connection) as $table) {
            foreach ($schema->getForeignKeys($table) as $key) {
                $parent = (string) $key['foreign_table'];

                if (count($key['columns']) !== 1 || (! isset($replaced[$table]) && ! isset($replaced[$parent]))) {
                    continue;
                }

                $column = (string) $key['columns'][0];
                $target = (string) $key['foreign_columns'][0];

                $count = $connection->table($table)
                    ->whereNotNull($table.'.'.$column)
                    ->whereNotExists(static function ($query) use ($parent, $target, $table, $column): void {
                        $query->selectRaw('1')->from($parent.' as webx_parent')->whereColumn('webx_parent.'.$target, $table.'.'.$column);
                    })
                    ->count();

                if ($count > 0) {
                    $found[] = ['table' => $table, 'column' => $column, 'parent' => $parent, 'count' => $count];
                }
            }
        }

        return $found;
    }

    /**
     * The archive's files onto the disk. A file that is already there with the same contents is
     * left alone; with `$keepExtra` false, anything that travels and is not in the archive goes.
     * Previews are deleted whenever a file changed or went, and are cut again on demand.
     *
     * @return array{added: int, replaced: int, unchanged: int, deleted: int}
     */
    public function restoreMedia(Manifest $manifest, string $staging, bool $keepExtra): array
    {
        $root = $this->media->root();
        $counts = ['added' => 0, 'replaced' => 0, 'unchanged' => 0, 'deleted' => 0];
        $archived = $manifest->mediaFiles();

        if (! is_dir($root)) {
            @mkdir($root, 0775, true);
        }

        foreach ($archived as $relative => $hash) {
            $source = $staging.'/'.Manifest::MEDIA.$relative;
            $target = $root.'/'.$relative;

            if (is_file($target)) {
                if (hash_equals($hash, (string) hash_file('sha256', $target))) {
                    $counts['unchanged']++;

                    continue;
                }

                $counts['replaced']++;
            } else {
                $counts['added']++;
            }

            if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
                throw SnapshotFailed::cannotWrite(dirname($target));
            }

            if (! @rename($source, $target) && ! (@copy($source, $target) && @unlink($source))) {
                throw SnapshotFailed::cannotWrite($target);
            }
        }

        if (! $keepExtra) {
            foreach ($this->media->files() as $relative) {
                if (! isset($archived[$relative]) && @unlink($root.'/'.$relative)) {
                    $counts['deleted']++;
                }
            }
        }

        if ($counts['replaced'] > 0 || $counts['deleted'] > 0) {
            foreach ($this->media->previewFolders() as $folder) {
                MediaDisk::deleteDirectory($folder);
            }
        }

        return $counts;
    }

    /**
     * @return list<array{command: string, parameters: array<string, mixed>}>
     */
    public function afterRestoreCommands(): array
    {
        return $this->tables->afterRestoreCommands();
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     * @return list<array<string, mixed>> Always empty: what is left to insert.
     */
    private static function insert(Connection $connection, ?string $table, array $batch): array
    {
        if ($batch !== [] && $table !== null) {
            $connection->table($table)->insert($batch);
        }

        return [];
    }

    /**
     * One at a time, so each takes the next id the table has rather than one the batch guessed.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>> Always empty.
     */
    private static function insertEach(Connection $connection, ?string $table, array $rows): array
    {
        foreach ($table === null ? [] : $rows as $row) {
            $connection->table($table)->insert($row);
        }

        return [];
    }

    /**
     * A path inside the media folder: no climbing out, no drive, no absolute start.
     */
    public static function safe(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\') || str_contains($relative, ':') || str_starts_with($relative, '/')) {
            return false;
        }

        return ! in_array('..', explode('/', $relative), true);
    }

    private function connection(): Connection
    {
        return $this->db->connection();
    }
}
