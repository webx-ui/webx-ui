<?php

declare(strict_types=1);

namespace WebxUi\Admin\Backups;

use RuntimeException;

/**
 * Why a dump did not happen.
 *
 * Every one of these means the same thing to the panel — last night produced no file — so the
 * message matters only to whoever reads the log, and it is written for them: what was being
 * run, and what it said before it gave up.
 */
final class BackupFailed extends RuntimeException
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self("There is no dump for a [{$driver}] connection: mysql, mariadb, pgsql and sqlite are what this command knows.");
    }

    public static function notALocalDisk(string $disk): self
    {
        return new self("The backup disk [{$disk}] is not a local one. A dump is written as a stream to a path, and is deliberately kept off anything the site serves.");
    }

    public static function unwritableDirectory(string $directory): self
    {
        return new self("The backup directory [{$directory}] could not be created.");
    }

    public static function unreadableDatabase(string $path): self
    {
        return new self("The SQLite database [{$path}] is not there to copy.");
    }

    public static function notRunnable(string $binary): self
    {
        return new self("[{$binary}] could not be started. Name the path to it in `webx-admin.backup.binary` — where it lives differs on every machine.");
    }

    public static function command(string $binary, int $status, string $errors): self
    {
        $said = $errors === '' ? '' : ' It said: '.$errors;

        return new self("[{$binary}] exited with {$status}.".$said);
    }

    public static function cannotWrite(string $path): self
    {
        return new self("The dump could not be written to [{$path}].");
    }
}
