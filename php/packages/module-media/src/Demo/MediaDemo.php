<?php

declare(strict_types=1);

namespace WebxUi\Media\Demo;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use SplFileInfo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\ThemeDemo;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Storage\FileStore;

/**
 * Two pictures in the library, so that a cover field and a gallery have something to point at
 * (§9 of the new-site spec) — and the pictures the site's theme brings in its `demo/media/`
 * (§15.1 of the themes spec): the showcase of `theme-default`, whose gallery and logos blocks
 * stand on them.
 *
 * They go in through {@see FileStore} rather than straight into the table: the key, the hash,
 * the dimensions and the bytes on the disk are all its work, and a demo that wrote the row by
 * hand would be the one place in the module where a file exists without them. Abstract
 * gradients on purpose — nothing in a public repository should look like a photograph of
 * somebody's client.
 *
 * A demo page names a picture by `demo:<name>` — the file's name without its extension — because
 * the key the library gives it is not known until it is stored; module-pages puts the key in
 * when it seeds the pages.
 */
final class MediaDemo
{
    /** Landscape for a cover, square for an avatar or a logo slot. */
    private const FILES = ['demo-wide.jpg', 'demo-square.jpg'];

    /** The kinds of file a theme's demo may bring, by extension. */
    private const TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];

    public function __construct(
        private readonly FileStore $store,
        private readonly Filesystem $files,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->orderBy('lft')->first();

        if (! $root instanceof MediaDirectory) {
            throw new RuntimeException('The library has no root folder; run the migrations first.');
        }

        foreach ([...array_map(static fn (string $name): string => __DIR__.'/../../resources/demo/'.$name, self::FILES), ...$this->themeFiles()] as $path) {
            $name = basename($path);

            // Test mode, because this is a file of the package and not something that came up
            // a socket: without it `UploadedFile` refuses anything PHP did not receive itself.
            $file = $this->store->store(new UploadedFile($path, $name, self::TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))], null, true), $root);

            // The same bytes were already in the library — somebody's own picture, or a second
            // run. It is not the demo's, so it is not the demo's to remove.
            if (! $file->wasRecentlyCreated) {
                continue;
            }

            $ledger->created($file, $file->name);
        }
    }

    /**
     * The pictures of the theme's `demo/media/`, in the order of their names; a file of a kind
     * the list above does not know is not a picture to seed.
     *
     * @return list<string>
     */
    private function themeFiles(): array
    {
        $directory = ThemeDemo::directory('media');

        if ($directory === null) {
            return [];
        }

        $found = array_values(array_filter(
            $this->files->files($directory),
            static fn (SplFileInfo $file): bool => isset(self::TYPES[strtolower($file->getExtension())]),
        ));
        $paths = array_map(static fn (SplFileInfo $file): string => $file->getPathname(), $found);
        sort($paths);

        return $paths;
    }
}
