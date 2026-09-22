<?php

declare(strict_types=1);

namespace WebxUi\Admin\Setup;

use RuntimeException;

/**
 * Something `webx:setup` will not guess its way past.
 *
 * Every message here ends with what to do about it: the command runs once, in front of somebody
 * who has never seen this application before, and "failed" on its own sends them looking through
 * a stack trace for a database name.
 */
final class SetupFailed extends RuntimeException
{
    public static function badDatabaseName(string $name): self
    {
        return new self(
            "[{$name}] is not a name a database can have. Letters, digits and underscores only — "
            .'pass --db=<name> with one of those.',
        );
    }

    /**
     * Only ever seen by a run with nobody in front of it.
     *
     * Asked interactively, this is a question rather than a failure — `webx:setup` says the
     * same thing and then asks where the server is. A script has nobody to ask, so it gets the
     * three ways of telling it instead.
     */
    public static function noDatabaseServer(string $host, string $port, string $username, string $refused): self
    {
        return new self(sprintf(
            'No answer from %s:%s as [%s] — %s. Start the server, or say where it is with '
            .'--db-host and --db-port, or pass --db-connection=sqlite to stand on a file. '
            .'sqlite hides three things a real server refuses, so it is worth a minute of '
            .'looking first.',
            $host,
            $port,
            $username,
            rtrim($refused, '. '),
        ));
    }

    public static function unknownModule(string $id, string $known): self
    {
        return new self("There is no module called [{$id}]. The ones there are: {$known}.");
    }

    public static function noEnv(string $path): self
    {
        return new self(
            "There is no {$path} to write to. Copy .env.example over it and run "
            .'`php artisan key:generate` first.',
        );
    }

    public static function step(string $what, int $status): self
    {
        return new self("{$what} exited with {$status}. Fix what it said and run `php artisan webx:setup` again — it carries on from where it stopped.");
    }
}
