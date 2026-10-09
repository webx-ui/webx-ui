<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\Paths;
use WebxUi\Admin\Support\Ownership;

/**
 * Whether the web server — not the shell running this — can write where the site writes at run time.
 *
 * {@see Storage} asks by writing a file, which answers for whoever runs the doctor. In a container
 * that is root, who can write anywhere, and the site still answers 500 on the first thumbnail it
 * cuts inside a folder a root-run command made. So this reads the owners and the mode bits and
 * answers for the web server's user: `webx-admin.web_user` when set, the owner of `storage` when not.
 *
 * Run as root it also lists what under `storage` belongs to somebody else — the trace a command
 * run without `-u` leaves. Without POSIX (Windows) there is nothing to ask, and it says nothing.
 */
final class StorageOwner implements Check
{
    /** Where the site writes while it runs: uploads, previews, caches, compiled views, logs. */
    private const RUNTIME = [
        'app/public',
        'app/public/media/thumbs',
        'app/private',
        'framework/cache',
        'framework/sessions',
        'framework/views',
        'logs',
    ];

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly Ownership $ownership,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $owner = $this->ownership->owner();

        if ($owner === null) {
            return [];
        }

        $user = trim((string) $this->config->get('webx-admin.web_user', ''));
        $uid = $user === '' ? $owner['uid'] : $this->ownership->uidOf($user);

        if ($uid === null) {
            return [Diagnosis::fail('Web server user', "webx-admin.web_user names [{$user}], and there is no such user here.")];
        }

        $name = $this->ownership->name($uid);
        $found = [];
        $closed = [];

        foreach (self::RUNTIME as $relative) {
            $path = $this->app->storagePath($relative);

            if (is_dir($path) && ! $this->ownership->writableBy($path, $uid)) {
                $closed[] = 'storage/'.$relative;
            }
        }

        if ($closed !== []) {
            $found[] = Diagnosis::fail(
                'Web server user',
                sprintf('%s cannot write into %s — run `chown -R %s storage` and keep running artisan as %s.', $name, implode(', ', $closed), $this->owner($owner), $name),
            );
        }

        if ($this->ownership->asRoot()) {
            $strangers = $this->ownership->strangers($this->app->storagePath());

            if ($strangers['count'] > 0) {
                $found[] = Diagnosis::fail(
                    'storage owner',
                    sprintf(
                        '%d paths under storage are not %s\'s (%s) — something ran as another user. Run `chown -R %s storage`, and artisan as %s (`docker exec -u %s …`).',
                        $strangers['count'],
                        $this->ownership->name($owner['uid']),
                        implode(', ', array_map(fn (string $path): string => Paths::short($path, $this->app->basePath()), $strangers['sample'])).($strangers['count'] > count($strangers['sample']) ? ', …' : ''),
                        $this->owner($owner),
                        $this->ownership->name($owner['uid']),
                        $this->ownership->name($owner['uid']),
                    ),
                );
            }
        }

        return $found !== [] ? $found : [Diagnosis::ok('Web server user', "{$name} can write everywhere the site writes at run time.")];
    }

    /**
     * @param  array{uid: int, gid: int}  $owner
     */
    private function owner(array $owner): string
    {
        $group = function_exists('posix_getgrgid') ? @posix_getgrgid($owner['gid']) : false;

        return $this->ownership->name($owner['uid']).':'.(is_array($group) ? (string) $group['name'] : (string) $owner['gid']);
    }
}
