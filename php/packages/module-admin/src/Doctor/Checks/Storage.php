<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as Disks;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\Paths;

/**
 * The two ways a site with a working library still shows broken images.
 *
 * `storage:link` is one command and is forgotten on every second deploy, and a link whose
 * target has been cleared out looks exactly like one that was never made. The other is
 * permissions: a directory created by a process running as somebody else — a backup written by
 * a cron container as root, say (CLAUDE.md §4) — is one the application cannot write into, and
 * nothing anywhere says so. Both are cheap to ask about, so this asks by writing a file.
 */
final class Storage implements Check
{
    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly Disks $disks,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        return [...$this->links(), ...$this->writable()];
    }

    /**
     * Every link the application declares, and whether it leads anywhere.
     *
     * @return list<Diagnosis>
     */
    private function links(): array
    {
        /** @var array<string, string> $links */
        $links = (array) $this->config->get('filesystems.links', []);
        $found = [];

        foreach ($links as $link => $target) {
            if (! is_string($link) || ! is_string($target)) {
                continue;
            }

            $name = basename($link);
            $where = Paths::short($target, $this->app->basePath());

            if (! $this->files->isDirectory($target)) {
                $found[] = Diagnosis::fail(
                    'storage:link',
                    "{$where} is not there, so public/{$name} leads nowhere — recreate it and run `php artisan storage:link`.",
                );

                continue;
            }

            // `realpath` rather than `is_link`: what breaks is the target, and a junction over
            // a directory that has been deleted answers neither as a link nor as a directory.
            $resolved = realpath($link);

            if ($resolved === false) {
                $found[] = Diagnosis::fail(
                    'storage:link',
                    "there is no public/{$name} — run `php artisan storage:link`.",
                );

                continue;
            }

            if ($resolved !== realpath($target)) {
                $found[] = Diagnosis::warn(
                    'storage:link',
                    "public/{$name} leads to ".Paths::short($resolved, $this->app->basePath())." rather than to {$where}.",
                );
            }
        }

        if ($found !== []) {
            return $found;
        }

        return [Diagnosis::ok('storage:link', match (count($links)) {
            0 => 'nothing to link.',
            1 => 'one link, and it leads where it should.',
            default => count($links).' links, all of them leading where they should.',
        })];
    }

    /**
     * Whether the library's disk takes a file.
     *
     * @return list<Diagnosis>
     */
    private function writable(): array
    {
        $name = $this->config->get('webx-media.disk');

        if (! is_string($name) || $name === '') {
            return [];
        }

        $prefix = trim((string) $this->config->get('webx-media.prefix', ''), '/');
        $probe = ($prefix === '' ? '' : $prefix.'/').'.webx-doctor';

        try {
            $disk = $this->disks->disk($name);
            $disk->put($probe, (string) time());
            $written = $disk->exists($probe);
            $disk->delete($probe);
        } catch (Throwable $failure) {
            return [Diagnosis::fail(
                'Media disk',
                "[{$name}] refused a file: ".$failure->getMessage(),
            )];
        }

        return $written
            ? [Diagnosis::ok('Media disk', "[{$name}] takes a file and gives it back.")]
            : [Diagnosis::fail(
                'Media disk',
                "[{$name}] accepted a file and then did not have it — check the disk's credentials and the permissions on its root.",
            )];
    }
}
