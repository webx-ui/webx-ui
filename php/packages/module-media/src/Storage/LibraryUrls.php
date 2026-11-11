<?php

declare(strict_types=1);

namespace WebxUi\Media\Storage;

use WebxUi\Admin\Contracts\AssetDownloads;
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Media\Screens\MediaFiles;

/**
 * The panel's question — "where do these keys live?" — answered by the library.
 *
 * The lookup is the one every media field already uses, so a page whose blocks hold both a
 * `wx-media` cover and an article full of pictures asks about all of them once.
 *
 * A link to a file asks for the name as well: `download=""` is the name the file was uploaded
 * under (`file_name`), not the key it is kept by — and not its display name, which a rename in
 * the library changes and which has no extension.
 */
final readonly class LibraryUrls implements AssetDownloads, AssetUrls
{
    public function __construct(
        private MediaFiles $files,
        private FileUrls $urls,
    ) {}

    /**
     * @param  list<string>  $paths
     * @return array<string, string|null>
     */
    public function urls(array $paths): array
    {
        $this->files->load($paths);

        $addresses = [];

        foreach ($paths as $path) {
            $file = $this->files->find($path);

            $addresses[$path] = $file === null ? null : $this->urls->url($file);
        }

        return $addresses;
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, array{url: string, name: string}|null>
     */
    public function downloads(array $paths): array
    {
        $this->files->load($paths);

        $downloads = [];

        foreach ($paths as $path) {
            $file = $this->files->find($path);

            $downloads[$path] = $file === null ? null : ['url' => $this->urls->url($file), 'name' => $file->file_name];
        }

        return $downloads;
    }
}
