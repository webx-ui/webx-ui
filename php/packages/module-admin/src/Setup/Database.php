<?php

declare(strict_types=1);

namespace WebxUi\Admin\Setup;

use PDO;
use PDOException;

/**
 * The step that is missing today, and the reason a local site quietly ends up on sqlite: it is
 * one step shorter.
 *
 * sqlite accepts three things a real server refuses — a foreign key it never enforces, a
 * default longer than its own column, migrations of two packages sorted into an order that
 * cannot be satisfied — so every one of those is found on the day of the deploy rather than on
 * the day the code was written. A site that stands on MariaDB from its first minute finds them
 * itself.
 *
 * The connection is made without a database name, because the database is usually what is
 * missing.
 */
final class Database
{
    public function __construct(
        private readonly string $driver,
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
    ) {}

    /** Null when the server answered; what it said when it did not. */
    public function unreachable(): ?string
    {
        try {
            $this->server();

            return null;
        } catch (PDOException $refused) {
            return $refused->getMessage();
        }
    }

    public function has(string $name): bool
    {
        $statement = $this->server()->prepare($this->driver === 'pgsql'
            ? 'select 1 from pg_database where datname = ?'
            : 'select 1 from information_schema.schemata where schema_name = ?');

        $statement->execute([$name]);

        return $statement->fetchColumn() !== false;
    }

    /** @throws SetupFailed */
    public function create(string $name): void
    {
        $this->server()->exec($this->driver === 'pgsql'
            ? 'create database "'.$this->quoteName($name).'" encoding \'UTF8\''
            : 'create database `'.$this->quoteName($name).'` character set utf8mb4 collate utf8mb4_unicode_ci');
    }

    /**
     * How many rows a table holds, or null when the table is not there.
     *
     * Asked over PDO rather than through Eloquent because this process booted before the `.env`
     * it has just written: the application it is running inside is still connected to whatever
     * the file said when the command started, if it is connected to anything at all.
     */
    public function count(string $database, string $table): ?int
    {
        try {
            $connection = $this->server("{$this->driver}:host={$this->host};port={$this->port};dbname={$database}");

            $count = $connection->query('select count(*) from '.$this->quoted($table))?->fetchColumn();

            return is_numeric($count) ? (int) $count : null;
        } catch (PDOException) {
            return null;
        }
    }

    /** A name that is about to be written into DDL, so it is this or nothing. */
    public static function isValidName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) === 1;
    }

    private function quoteName(string $name): string
    {
        if (! self::isValidName($name)) {
            throw SetupFailed::badDatabaseName($name);
        }

        return $name;
    }

    private function quoted(string $table): string
    {
        return $this->driver === 'pgsql' ? '"'.$this->quoteName($table).'"' : '`'.$this->quoteName($table).'`';
    }

    private function server(?string $dsn = null): PDO
    {
        return new PDO(
            $dsn ?? "{$this->driver}:host={$this->host};port={$this->port}",
            $this->username,
            $this->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
    }
}
