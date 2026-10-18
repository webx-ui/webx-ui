<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

use RuntimeException;

/**
 * Whether a disk serves its files to anyone who knows the address, asked before a module puts
 * a stranger's file or an export there (§14.5 of the permissions spec).
 *
 * A module that keeps private files — attachments of a submission, a catalogue export — serves
 * them itself behind its own permission. That promise is only as good as the disk underneath:
 * `WEBX_INBOX_DISK=public` would put every résumé under `/storage/inbox/...`, and no check in
 * the panel would ever be asked. So the setting is refused, loudly, at the first write, rather
 * than trusted.
 *
 * Public means what Laravel means by it: `visibility: public`, or a local root inside the web
 * root or inside `storage/app/public`, which `storage:link` publishes. A bucket made public by
 * its own policy cannot be seen from here; the configuration is all there is to read.
 */
final class PrivateDisk
{
    /**
     * @param  array<string, mixed>  $disks  `filesystems.disks`
     */
    public function __construct(
        private readonly array $disks,
        private readonly string $publicPath,
        private readonly string $publicStorage,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (array) config('filesystems.disks', []),
            public_path(),
            storage_path('app/public'),
        );
    }

    public function isPublic(string $disk): bool
    {
        $config = $this->disks[$disk] ?? null;

        if (! is_array($config)) {
            return false;
        }

        if (($config['visibility'] ?? null) === 'public') {
            return true;
        }

        if (($config['driver'] ?? null) !== 'local' || ! is_string($config['root'] ?? null)) {
            return false;
        }

        $root = $this->normalise($config['root']);

        foreach ([$this->publicPath, $this->publicStorage] as $published) {
            $published = $this->normalise($published);

            if ($root === $published || str_starts_with($root.'/', $published.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string  $setting  where the disk was named, for the message: `webx-inbox.disk`
     *
     * @throws RuntimeException
     */
    public function assert(string $disk, string $setting): void
    {
        if ($this->isPublic($disk)) {
            throw new RuntimeException(
                "The disk [{$disk}] named by [{$setting}] is public: the files kept there would be served to anyone "
                .'who knows the address, past the permission that guards them in the panel. Name a private disk, such as [local].',
            );
        }
    }

    private function normalise(string $path): string
    {
        $real = realpath($path);

        return rtrim(str_replace('\\', '/', $real === false ? $path : $real), '/');
    }
}
