<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\Paths;
use WebxUi\Admin\Uploads\FreeSpace;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Admin\Uploads\Uploads;

/**
 * Whether the pieces of a chunked upload have somewhere to go (§4 of the video spec).
 *
 * Asked only when a module has registered something to upload: the directory has to take a
 * file, and the disk has to hold at least the largest file anybody may send — an upload refused
 * at the start is an annoyance, the same refusal found on a deploy is a fix before anybody hits it.
 */
final class UploadSpace implements Check
{
    public function __construct(
        private readonly Application $app,
        private readonly UploadPurposes $purposes,
        private readonly Uploads $uploads,
        private readonly FreeSpace $space,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $purposes = $this->purposes->all();

        if ($purposes === []) {
            return [];
        }

        $directory = $this->uploads->directory();
        $where = Paths::short($directory, $this->app->basePath());

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return [Diagnosis::fail('Uploads', "{$where} cannot be created — check the permissions on storage/app.")];
        }

        $probe = $directory.DIRECTORY_SEPARATOR.'.webx-doctor';

        if (@file_put_contents($probe, (string) time()) === false) {
            return [Diagnosis::fail('Uploads', "{$where} does not take a file — check who owns it.")];
        }

        @unlink($probe);

        $largest = max(array_map(static fn ($purpose): int => $purpose->maxBytes ?? 0, $purposes));
        $free = $this->space->bytes($directory);
        $chunk = $this->uploads->chunkSize();
        $pieces = 'pieces of '.self::size($chunk);

        if ($free !== null && $largest > 0 && $free < $largest) {
            return [Diagnosis::warn(
                'Uploads',
                "{$where} has ".self::size($free).' free, less than the largest file an upload may bring ('.self::size($largest).').',
            )];
        }

        return [Diagnosis::ok(
            'Uploads',
            $free === null ? "{$where} takes files, in {$pieces}." : "{$where} takes files, in {$pieces}, with ".self::size($free).' free.',
        )];
    }

    private static function size(int $bytes): string
    {
        return $bytes >= 1073741824
            ? round($bytes / 1073741824, 1).' GB'
            : round($bytes / 1048576, 1).' MB';
    }
}
