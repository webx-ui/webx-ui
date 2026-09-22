<?php

declare(strict_types=1);

namespace WebxUi\Admin\Setup;

/**
 * Where the database server is and who to connect as.
 *
 * The one answer `webx:setup` used to take without ever asking for it. The database *name* was
 * a question; the address was whatever `.env` happened to say, or `127.0.0.1:3306` as `root`,
 * which is right on most machines and wrong on the ones where it matters — OSPanel binds every
 * one of its database modules to a loopback address of its own. There the person answered six
 * questions and then watched the run stop at the step that had already rewritten `.env`, on a
 * host nobody had offered them the chance to name.
 *
 * So the address is asked for as well, but only when it has to be: the command reaches for the
 * server before it asks anything about it, and a machine that answers is never asked.
 */
final class Connection
{
    public function __construct(
        public readonly string $connection,
        public readonly string $host,
        public readonly string $port,
        public readonly string $username,
        public readonly string $password,
    ) {}

    /**
     * What the options say, then what the file says, then what is true on most machines.
     *
     * The password is read plainly rather than through `chosen()`: there is no placeholder the
     * skeleton ships for it to be mistaken for, and an empty password is a real answer — it is
     * the one MariaDB out of OSPanel gives `root`.
     *
     * @param  array<string, string|null>  $options  `--db-host` and its friends, null where unsaid
     */
    public static function resolve(string $connection, array $options, EnvFile $env): self
    {
        return new self(
            $connection,
            $options['host'] ?? $env->chosen('DB_HOST') ?? '127.0.0.1',
            $options['port'] ?? $env->chosen('DB_PORT') ?? '3306',
            $options['username'] ?? $env->chosen('DB_USERNAME') ?? 'root',
            $options['password'] ?? $env->get('DB_PASSWORD') ?? '',
        );
    }

    /**
     * The same server with whatever of this was actually answered.
     *
     * An empty answer keeps what was tried, and that is what makes the password askable at
     * all: a hidden field in Laravel Prompts has no default, so Enter has to mean "the one
     * already in the file" rather than "no password" — otherwise being asked where the server
     * is would cost you the password that was right all along.
     */
    public function with(string $host = '', string $port = '', string $username = '', string $password = ''): self
    {
        return new self(
            $this->connection,
            $host !== '' ? $host : $this->host,
            $port !== '' ? $port : $this->port,
            $username !== '' ? $username : $this->username,
            $password !== '' ? $password : $this->password,
        );
    }

    public function isSqlite(): bool
    {
        return $this->connection === 'sqlite';
    }

    /** `mariadb` is a Laravel connection; PDO has never heard of it. */
    public function driver(): string
    {
        return $this->connection === 'mariadb' ? 'mysql' : $this->connection;
    }

    public function database(): Database
    {
        return new Database($this->driver(), $this->host, (int) $this->port, $this->username, $this->password);
    }

    /** Null when the server answered; what it said when it did not. */
    public function unreachable(): ?string
    {
        return $this->database()->unreachable();
    }

    /**
     * How this reads in `.env`, in the order the skeleton has the names in.
     *
     * @return array<string, string>
     */
    public function env(string $database): array
    {
        return [
            'DB_HOST' => $this->host,
            'DB_PORT' => $this->port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $this->username,
            'DB_PASSWORD' => $this->password,
        ];
    }
}
