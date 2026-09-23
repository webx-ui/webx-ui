<?php

declare(strict_types=1);

namespace WebxUi\Admin\Backups;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;

/**
 * The backup directory, and the one thing that fills it.
 *
 * This is insurance, not a restore system (§1 of the backups spec): the file lands beside the
 * database it came from, so it survives a mistake and not a dead server. What it is for is
 * getting yesterday's version of one row, one table or one article back by hand.
 */
final class Backups
{
    public function __construct(
        private readonly Repository $config,
        private readonly DatabaseManager $db,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->setting('enabled', true);
    }

    /**
     * The time of night the schedule runs at, as `HH:MM`.
     */
    public function at(): string
    {
        $at = (string) $this->setting('at', '03:10');

        return preg_match('/^\d{1,2}:\d{2}$/', $at) === 1 ? $at : '03:10';
    }

    public function keep(): int
    {
        return max(1, (int) $this->setting('keep', 30));
    }

    /**
     * Where the files go: `path` under the root of the `disk`. Deliberately a local disk and
     * nothing else: a dump is the whole database in one file — password hashes, the telephone
     * numbers on every enquiry, tokens — and it belongs somewhere nothing serves (§5).
     *
     * The root is the disk's, not a path of ours, so it moves when Laravel moves it: on 11+
     * `local` is rooted at `storage/app/private`, and that is where the backups are. Naming a
     * literal path anywhere is how that stops being true without anybody noticing.
     */
    public function directory(): string
    {
        $disk = (string) $this->setting('disk', 'local');

        if ($this->config->get("filesystems.disks.{$disk}.driver") !== 'local') {
            throw BackupFailed::notALocalDisk($disk);
        }

        $root = rtrim((string) $this->config->get("filesystems.disks.{$disk}.root"), '/\\');
        $path = trim((string) $this->setting('path', 'backups'), '/\\');

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    /**
     * Every snapshot there is, newest first.
     *
     * @return list<Snapshot>
     */
    public function all(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'*.gz') ?: [];
        $snapshots = [];

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $snapshots[] = new Snapshot(
                $file,
                (new DateTimeImmutable('@'.filemtime($file)))->setTimezone(new DateTimeZone('UTC')),
                (int) filesize($file),
            );
        }

        usort($snapshots, static fn (Snapshot $a, Snapshot $b): int => $b->takenAt <=> $a->takenAt);

        return $snapshots;
    }

    public function latest(): ?Snapshot
    {
        return $this->all()[0] ?? null;
    }

    /**
     * Makes one. Either a whole file appears or nothing does: a dump that died halfway is
     * deleted rather than left to be the newest file in the directory, which is the one way
     * this could lie about when it last worked.
     */
    public function take(): Snapshot
    {
        $directory = $this->directory();

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw BackupFailed::unwritableDirectory($directory);
        }

        $connection = $this->db->connection();
        $dumper = new Dumper($connection, $this->settings());

        $path = $directory.DIRECTORY_SEPARATOR.sprintf(
            '%s-%s%s',
            $this->label((string) $connection->getDatabaseName()),
            (new DateTimeImmutable)->format('Y-m-d-Hi'),
            $dumper->extension(),
        );

        try {
            $dumper->writeTo($path);
        } catch (BackupFailed $failure) {
            @unlink($path);

            throw $failure;
        }

        @chmod($path, 0600);

        return new Snapshot(
            $path,
            (new DateTimeImmutable('@'.filemtime($path)))->setTimezone(new DateTimeZone('UTC')),
            (int) filesize($path),
        );
    }

    /**
     * Drops everything older than `$keep` days and says how many went.
     *
     * The newest file is never one of them. After a dump it cannot be — it was made a second
     * ago — but the guard is what makes this safe to run on a directory where the schedule has
     * been broken for a month: clearing out the last copy there is would be the worst thing
     * this code could do.
     *
     * @return list<string> The names of the files that were removed.
     */
    public function prune(?int $keep = null): array
    {
        $cutoff = (new DateTimeImmutable)->modify('-'.($keep ?? $this->keep()).' days');
        $snapshots = $this->all();
        $removed = [];

        foreach (array_slice($snapshots, 1) as $snapshot) {
            if ($snapshot->takenAt < $cutoff && @unlink($snapshot->path)) {
                $removed[] = $snapshot->name();
            }
        }

        return $removed;
    }

    /**
     * The database's name, safe to put in a file name. SQLite's is a path, and what is wanted
     * from it is the file it ends in.
     */
    private function label(string $database): string
    {
        $name = pathinfo($database, PATHINFO_FILENAME);
        $name = (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name);

        return trim($name, '-') === '' ? 'database' : trim($name, '-');
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = $this->config->get('webx-admin.backup', []);

        return is_array($settings) ? $settings : [];
    }

    private function setting(string $key, mixed $default): mixed
    {
        return $this->settings()[$key] ?? $default;
    }
}
