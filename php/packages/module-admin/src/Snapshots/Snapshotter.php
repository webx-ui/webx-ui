<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

use Composer\InstalledVersions;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Makes an archive: the content tables as JSON lines, the media files, and a manifest.
 *
 * Not `Dumper`. A native dump is locked to its driver, carries each table's structure with its
 * rows — so a restore could only replace a table whole, structure included, even when the
 * target's code has moved the table on — and on SQLite it is a copy of the file, which cannot be
 * split into the tables that travel and the ones that stay. Rows as data can be put into whatever
 * shape the target has, column by column, and a laptop on SQLite can take a server's MySQL
 * content. `Dumper` still does what it is for: the rollback dump `webx:db:backup` writes before
 * a restore.
 */
final class Snapshotter
{
    public function __construct(
        private readonly Repository $config,
        private readonly DatabaseManager $db,
        private readonly SnapshotTables $tables,
        private readonly MediaDisk $media,
    ) {}

    public function directory(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.'snapshots');
    }

    public function defaultPath(): string
    {
        $site = Str::slug((string) $this->config->get('app.name', 'site')) ?: 'site';
        $env = Str::slug((string) $this->config->get('app.env', 'production')) ?: 'env';

        return $this->directory().DIRECTORY_SEPARATOR.sprintf('%s-%s-%s.tar.gz', $site, $env, (new DateTimeImmutable)->format('Y-m-d-His'));
    }

    /**
     * @return array{path: string, manifest: Manifest, undeclared: list<string>}
     */
    public function make(string $path, bool $all = false, bool $withAdmins = false, bool $media = true): array
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw SnapshotFailed::cannotWrite($directory);
        }

        $connection = $this->db->connection();
        $database = tempnam(sys_get_temp_dir(), 'webx-snap-');

        if ($database === false) {
            throw SnapshotFailed::cannotWrite(sys_get_temp_dir());
        }

        try {
            $undeclared = [];
            $tables = [];

            foreach (self::tablesOf($connection) as $table) {
                $group = $this->tables->groupOf($table);

                if ($group === null) {
                    $undeclared[] = $table;
                }

                if ($this->tables->travels($table, $all, $withAdmins)) {
                    $tables[$table] = ['group' => $group?->value];
                }
            }

            $tables = $this->writeRows($connection, $tables, $database);
            $files = $media ? $this->media->files() : [];
            $root = $media ? $this->media->root() : '';
            $hashes = [];
            $bytes = 0;

            foreach ($files as $file) {
                $hashes[$file] = (string) hash_file('sha256', $root.'/'.$file);
                $bytes += (int) filesize($root.'/'.$file);
            }

            $manifest = new Manifest([
                'format' => Manifest::FORMAT,
                'version' => Manifest::VERSION,
                'site' => (string) $this->config->get('app.name'),
                'env' => (string) $this->config->get('app.env'),
                'url' => (string) $this->config->get('app.url'),
                'created_at' => (new DateTimeImmutable)->format(DATE_ATOM),
                'driver' => $connection->getDriverName(),
                'options' => ['all' => $all, 'with_admins' => $withAdmins, 'media' => $media],
                'packages' => $this->packages(),
                'migrations' => $this->ranMigrations($connection),
                'tables' => $tables,
                'undeclared' => $undeclared,
                'media' => $media ? ['disk' => $this->media->disk(), 'count' => count($files), 'bytes' => $bytes, 'files' => $hashes] : null,
            ]);

            $tar = Tar::create($path);

            try {
                $tar->addString(Manifest::NAME, $manifest->toJson());
                $tar->addFile(Manifest::DATABASE, $database);

                foreach ($files as $file) {
                    $tar->addFile(Manifest::MEDIA.$file, $root.'/'.$file);
                }

                $tar->close();
            } catch (SnapshotFailed $failure) {
                @unlink($path);

                throw $failure;
            }

            @chmod($path, 0600);

            return ['path' => $path, 'manifest' => $manifest, 'undeclared' => $undeclared];
        } finally {
            @unlink($database);
        }
    }

    /**
     * The tables of this database, and only of this database — see `Dumper::tables()` for why
     * the schema has to be named.
     *
     * @return list<string>
     */
    public static function tablesOf(Connection $connection): array
    {
        $schema = $connection->getSchemaBuilder();

        $names = array_map(
            static fn (array $table): string => (string) $table['name'],
            $schema->getTables($schema->getCurrentSchemaName()),
        );

        sort($names);

        return $names;
    }

    /**
     * One line naming a table and its columns, then one line per row: a JSON array in the
     * column order. A string that is not UTF-8 (a binary column) is written as `{"b64": …}` —
     * the only object that can appear in a row, so reading it back is unambiguous.
     *
     * @param  array<string, array{group: string|null}>  $tables
     * @return array<string, array{group: string|null, rows: int, columns: list<string>}>
     */
    private function writeRows(Connection $connection, array $tables, string $path): array
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw SnapshotFailed::cannotWrite($path);
        }

        $written = [];

        try {
            foreach ($tables as $table => $meta) {
                $columns = $connection->getSchemaBuilder()->getColumnListing($table);
                $rows = 0;

                fwrite($handle, json_encode(['table' => $table, 'columns' => $columns], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

                foreach ($connection->table($table)->cursor() as $row) {
                    $values = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column} ?? null;
                        $values[] = is_string($value) && ! mb_check_encoding($value, 'UTF-8') ? ['b64' => base64_encode($value)] : $value;
                    }

                    $line = json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

                    if ($line === false || fwrite($handle, $line."\n") === false) {
                        throw SnapshotFailed::cannotWrite($path);
                    }

                    $rows++;
                }

                $written[$table] = ['group' => $meta['group'], 'rows' => $rows, 'columns' => $columns];
            }
        } finally {
            fclose($handle);
        }

        return $written;
    }

    /**
     * @return list<string>
     */
    private function ranMigrations(Connection $connection): array
    {
        $table = $this->tables->migrationsTable();

        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return [];
        }

        return array_values(array_map(strval(...), $connection->table($table)->orderBy('id')->pluck('migration')->all()));
    }

    /**
     * @return array<string, string>
     */
    private function packages(): array
    {
        if (! class_exists(InstalledVersions::class)) {
            return [];
        }

        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            if (str_starts_with($package, 'webx-ui/')) {
                $packages[$package] = (string) InstalledVersions::getPrettyVersion($package);
            }
        }

        ksort($packages);

        return $packages;
    }
}
