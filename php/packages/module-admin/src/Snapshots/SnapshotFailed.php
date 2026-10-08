<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

use RuntimeException;

/**
 * Why a snapshot was not made or not restored. Every message is written for the person at the
 * console, and says what to do when there is something to do.
 */
final class SnapshotFailed extends RuntimeException
{
    public static function cannotOpen(string $path): self
    {
        return new self("[{$path}] could not be opened as a gzipped archive.");
    }

    public static function cannotRead(string $path): self
    {
        return new self("[{$path}] could not be read.");
    }

    public static function cannotWrite(string $path): self
    {
        return new self("[{$path}] could not be written.");
    }

    public static function truncated(string $path): self
    {
        return new self("[{$path}] ends in the middle of a file: it was cut short, most likely while being copied. Copy it again.");
    }

    public static function notASnapshot(string $path): self
    {
        return new self("[{$path}] is not a WebX UI snapshot: its first file is not manifest.json.");
    }

    public static function unknownFormat(string $format, string $version, string $supported): self
    {
        return new self("The archive is in format [{$format} {$version}], and this code reads [webx-snapshot {$supported}]. Update webx-ui/module-admin on this stand, or make the snapshot with the same version.");
    }

    /**
     * @param  list<string>  $migrations
     */
    public static function unknownMigrations(array $migrations): self
    {
        $shown = implode(', ', array_slice($migrations, 0, 5)).(count($migrations) > 5 ? ', …' : '');

        return new self('The archive was made by newer code: '.count($migrations)." of its migrations do not exist here ({$shown}). Deploy the code first, then restore.");
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self("Restoring into a [{$driver}] connection is not supported: mysql, mariadb and sqlite are. A snapshot can still be made here.");
    }

    public static function notALocalDisk(string $disk): self
    {
        return new self("The media disk [{$disk}] is not a local one. Run with --no-media, or point `webx-admin.snapshot.disk` at a local disk.");
    }

    public static function missingEntry(string $name): self
    {
        return new self("The archive has no [{$name}] although its manifest says it should. Make the snapshot again.");
    }

    public static function corrupted(string $file): self
    {
        return new self("[{$file}] in the archive does not match the hash in its manifest. The archive was damaged on the way; copy it again.");
    }

    public static function unsafePath(string $name): self
    {
        return new self("The archive names a file outside its own folders: [{$name}]. Refusing to write it.");
    }
}
