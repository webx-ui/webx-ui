<?php

declare(strict_types=1);

namespace WebxUi\Media\Demo;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Storage\FileStore;

/**
 * Two pictures in the library, so that a cover field and a gallery have something to point at
 * (§9 of the new-site spec).
 *
 * They go in through {@see FileStore} rather than straight into the table: the key, the hash,
 * the dimensions and the bytes on the disk are all its work, and a demo that wrote the row by
 * hand would be the one place in the module where a file exists without them. Abstract
 * gradients on purpose — nothing in a public repository should look like a photograph of
 * somebody's client.
 */
final class MediaDemo
{
    /** Landscape for a cover, square for an avatar or a logo slot. */
    private const FILES = ['demo-wide.jpg', 'demo-square.jpg'];

    public function __construct(private readonly FileStore $store) {}

    public function seed(DemoLedger $ledger): void
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->orderBy('lft')->first();

        if (! $root instanceof MediaDirectory) {
            throw new RuntimeException('The library has no root folder; run the migrations first.');
        }

        foreach (self::FILES as $name) {
            $path = __DIR__.'/../../resources/demo/'.$name;

            // Test mode, because this is a file of the package and not something that came up
            // a socket: without it `UploadedFile` refuses anything PHP did not receive itself.
            $file = $this->store->store(new UploadedFile($path, $name, 'image/jpeg', null, true), $root);

            // The same bytes were already in the library — somebody's own picture, or a second
            // run. It is not the demo's, so it is not the demo's to remove.
            if (! $file->wasRecentlyCreated) {
                continue;
            }

            $ledger->created($file, $file->name);
        }
    }
}
