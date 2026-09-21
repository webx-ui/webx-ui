<?php

declare(strict_types=1);

namespace WebxUi\Admin\Backups;

/**
 * Runs one command and gzips what it prints on the way past.
 *
 * The point of doing it here rather than through a shell pipeline is that no uncompressed copy
 * of the database ever exists: a site whose dump is 400 MB does not need 400 MB of free disk to
 * make a 40 MB file, and nothing readable is left behind if the run is interrupted.
 *
 * Standard error goes to a file rather than a second pipe on purpose. Two pipes have to be
 * drained together or the child blocks when one of them fills, and the non-blocking reads that
 * needs behave differently on Windows — where this is developed. A file cannot fill and cannot
 * deadlock, and what it holds is only read when the exit code says something went wrong.
 */
final class Pipe
{
    /** Big enough that a dump of any size is a few thousand reads rather than a few million. */
    private const CHUNK = 262144;

    /**
     * @param  list<string>  $argv  The binary and its arguments, unescaped: `proc_open` is given
     *                              the array and does the quoting for the platform itself.
     * @param  resource  $gz  An open gzip handle; left open, because several commands write one file.
     * @param  array<string, string>|null  $env  Added to the environment the process inherits.
     */
    public static function run(array $argv, $gz, ?array $env = null): void
    {
        $errors = tempnam(sys_get_temp_dir(), 'webx-dump-');

        if ($errors === false) {
            throw BackupFailed::cannotWrite(sys_get_temp_dir());
        }

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']];
        $process = @proc_open($argv, $descriptors, $pipes, null, $env === null ? null : $env + getenv());

        if (! is_resource($process)) {
            @unlink($errors);

            throw BackupFailed::notRunnable($argv[0]);
        }

        // Nothing is being fed in, and a dump tool that finds a pipe on its input may wait on it.
        fclose($pipes[0]);

        try {
            while (! feof($pipes[1])) {
                $chunk = fread($pipes[1], self::CHUNK);

                if ($chunk === false) {
                    break;
                }

                if ($chunk !== '' && gzwrite($gz, $chunk) === false) {
                    throw BackupFailed::cannotWrite('the backup file');
                }
            }
        } finally {
            fclose($pipes[1]);
            $status = proc_close($process);
        }

        if ($status !== 0) {
            $said = trim((string) @file_get_contents($errors));
            @unlink($errors);

            throw BackupFailed::command($argv[0], $status, $said);
        }

        @unlink($errors);
    }
}
