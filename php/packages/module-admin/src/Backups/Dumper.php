<?php

declare(strict_types=1);

namespace WebxUi\Admin\Backups;

use Illuminate\Database\Connection;

/**
 * The dump itself: one connection, one gzipped file, whichever tool the driver calls for.
 *
 * The flags are not decoration. `--single-transaction --quick` is what stops a nightly dump
 * from locking the tables and taking the site down with it; `--no-tablespaces` is what lets it
 * run as a shared-hosting user, who has no `PROCESS` privilege and without the flag fails on
 * the first table; `--default-character-set=utf8mb4` is what keeps the translated JSON columns
 * from coming back as mojibake.
 *
 * The password never appears in an argument. `-p<password>` is visible in `ps` to anybody with
 * a shell on the machine, so MySQL is handed a defaults file and PostgreSQL a `.pgpass`, both
 * written 0600 and removed in a `finally`.
 */
final class Dumper
{
    /**
     * @param  array<string, mixed>  $settings  The `webx-admin.backup` section.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly array $settings,
    ) {}

    /**
     * What the file this driver produces should be called after the date in its name.
     */
    public function extension(): string
    {
        return $this->connection->getDriverName() === 'sqlite' ? '.sqlite.gz' : '.sql.gz';
    }

    public function writeTo(string $path): void
    {
        match ($driver = $this->connection->getDriverName()) {
            'mysql', 'mariadb' => $this->mysql($path),
            'pgsql' => $this->postgres($path),
            'sqlite' => $this->sqlite($path),
            default => throw BackupFailed::unsupportedDriver($driver),
        };
    }

    /**
     * Two runs, and the order of them is the whole reason this is not one.
     *
     * `mysqldump` has no way to say "every table's structure, most tables' data", so the tables
     * that keep their data are dumped first — structure and rows table by table, the way a
     * single run lays them out — and the throwaway ones follow with `--no-data`. Pulling one
     * table out of the finished file is then a single contiguous range between two
     * `-- Table structure for table` lines, which is the one thing a backup nobody can restore
     * from has to be good at (§7).
     */
    private function mysql(string $path): void
    {
        ['kept' => $kept, 'skipped' => $skipped] = self::split($this->tables(), $this->skipData());

        $defaults = $this->mysqlDefaultsFile();

        try {
            $this->writing($path, function ($gz) use ($defaults, $kept, $skipped): void {
                if ($kept !== []) {
                    Pipe::run([...$this->mysqlCommand($defaults), ...$kept], $gz);
                }

                if ($skipped !== []) {
                    Pipe::run([...$this->mysqlCommand($defaults, structureOnly: true), ...$skipped], $gz);
                }
            });
        } finally {
            @unlink($defaults);
        }
    }

    /**
     * Which tables keep their rows and which keep only their shape.
     *
     * A name in `skip_data` that no longer exists is left out rather than passed on: naming a
     * missing table on the command line is how the whole dump fails over a table somebody
     * dropped two releases ago.
     *
     * @param  list<string>  $tables
     * @param  list<string>  $skipData
     * @return array{kept: list<string>, skipped: list<string>}
     */
    public static function split(array $tables, array $skipData): array
    {
        $skipped = array_values(array_intersect($tables, $skipData));

        return ['kept' => array_values(array_diff($tables, $skipped)), 'skipped' => $skipped];
    }

    /**
     * The `mysqldump` call, without the table names. Public so that what it asks for can be
     * read — and asserted — without a server to ask.
     *
     * @return list<string>
     */
    public function mysqlCommand(string $defaults, bool $structureOnly = false): array
    {
        // `--defaults-extra-file` is only read when it comes first; MySQL rejects it anywhere else.
        $argv = [
            $this->binary('mysqldump'),
            '--defaults-extra-file='.$defaults,
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
        ];

        // A setting rather than a guess: a mysqldump 8 client asks a MariaDB server for column
        // statistics and dies on the answer, while MariaDB's own client does not know the flag
        // that turns it off and dies on that. Only the machine knows which pair it has.
        $statistics = $this->settings['column_statistics'] ?? null;

        if ($statistics === false) {
            $argv[] = '--skip-column-statistics';
        } elseif ($statistics === true) {
            $argv[] = '--column-statistics=1';
        }

        if ($structureOnly) {
            $argv[] = '--no-data';
        }

        foreach ($this->options() as $option) {
            $argv[] = $option;
        }

        $argv[] = $this->connection->getDatabaseName();

        return $argv;
    }

    /**
     * The file the password travels in. Public for the same reason as the command above: that
     * the password is here and nowhere on a command line is the thing worth checking.
     *
     * @return string The path of the file the caller has to delete.
     */
    public function mysqlDefaultsFile(): string
    {
        $config = $this->connection->getConfig();
        $lines = ['[client]'];

        foreach ([
            'user' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'socket' => $config['unix_socket'] ?? null,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Option files take a quoted value with backslash escapes, which is the only form
            // that survives a password with a space or a `#` in it.
            $lines[] = $key.'="'.addcslashes((string) $value, '\\"').'"';
        }

        return $this->secretFile('webx-my-', implode("\n", $lines)."\n");
    }

    /**
     * One run: `--exclude-table-data` is the thing `mysqldump` is missing, so PostgreSQL needs
     * no second pass.
     */
    private function postgres(string $path): void
    {
        $config = $this->connection->getConfig();
        ['skipped' => $skipped] = self::split($this->tables(), $this->skipData());

        $argv = [
            $this->binary('pg_dump'),
            '--no-owner',
            '--no-privileges',
            '--format=plain',
            '--encoding=UTF8',
        ];

        foreach ($skipped as $table) {
            $argv[] = '--exclude-table-data='.$table;
        }

        foreach (['host' => 'host', 'port' => 'port', 'username' => 'username'] as $key => $option) {
            if (($config[$key] ?? null) !== null && $config[$key] !== '') {
                $argv[] = '--'.$option.'='.$config[$key];
            }
        }

        foreach ($this->options() as $option) {
            $argv[] = $option;
        }

        $argv[] = $this->connection->getDatabaseName();

        // Same reason as the MySQL defaults file: an environment variable is better than an
        // argument, and a 0600 file is better than an environment variable.
        $pgpass = $this->secretFile('webx-pg-', implode(':', [
            $this->escapePgpass((string) ($config['host'] ?? 'localhost')),
            $this->escapePgpass((string) ($config['port'] ?? '5432')),
            $this->escapePgpass((string) $this->connection->getDatabaseName()),
            $this->escapePgpass((string) ($config['username'] ?? '')),
            $this->escapePgpass((string) ($config['password'] ?? '')),
        ])."\n");

        try {
            $this->writing($path, static function ($gz) use ($argv, $pgpass): void {
                Pipe::run($argv, $gz, ['PGPASSFILE' => $pgpass]);
            });
        } finally {
            @unlink($pgpass);
        }
    }

    private function escapePgpass(string $field): string
    {
        return addcslashes($field, '\\:');
    }

    /**
     * A copy of the file, gzipped. `skip_data` is not honoured here and cannot be: what is
     * being copied is the database, not a description of it.
     */
    private function sqlite(string $path): void
    {
        $source = (string) $this->connection->getDatabaseName();
        $handle = is_file($source) ? fopen($source, 'rb') : false;

        if ($handle === false) {
            throw BackupFailed::unreadableDatabase($source);
        }

        try {
            $this->writing($path, static function ($gz) use ($handle, $path): void {
                while (! feof($handle)) {
                    $chunk = fread($handle, 262144);

                    if ($chunk === false) {
                        break;
                    }

                    if ($chunk !== '' && gzwrite($gz, $chunk) === false) {
                        throw BackupFailed::cannotWrite($path);
                    }
                }
            });
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  callable(resource): void  $write
     */
    private function writing(string $path, callable $write): void
    {
        $gz = gzopen($path, 'wb6');

        if ($gz === false) {
            throw BackupFailed::cannotWrite($path);
        }

        try {
            $write($gz);
        } finally {
            gzclose($gz);
        }
    }

    /**
     * A file only this process can read, holding a password. Written before the tool that
     * needs it starts and deleted whatever happens next.
     */
    private function secretFile(string $prefix, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false || file_put_contents($path, $contents) === false) {
            throw BackupFailed::cannotWrite(sys_get_temp_dir());
        }

        @chmod($path, 0600);

        return $path;
    }

    /**
     * The tables of this database, and only of this database.
     *
     * `getTables()` with nothing in its hand asks for every schema the server has — on a
     * development machine that is every other site on it — and the dump then dies on the first
     * table it is told to dump and cannot see. The schema to ask for is the driver's own idea
     * of the current one: the database name on MySQL, `public` on PostgreSQL, `main` on SQLite.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $schema = $this->connection->getSchemaBuilder();

        $names = array_map(
            static fn (array $table): string => (string) $table['name'],
            $schema->getTables($schema->getCurrentSchemaName()),
        );

        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function skipData(): array
    {
        $tables = $this->settings['skip_data'] ?? [];

        return array_values(array_map(strval(...), is_array($tables) ? $tables : []));
    }

    /**
     * @return list<string>
     */
    private function options(): array
    {
        $options = $this->settings['options'] ?? [];

        return array_values(array_map(strval(...), is_array($options) ? $options : []));
    }

    private function binary(string $default): string
    {
        $named = $this->settings['binary'] ?? null;

        return is_string($named) && $named !== '' ? $named : $default;
    }
}
