<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

/**
 * Where a library file is served from, and what it was called when it was uploaded.
 *
 * The half of {@see AssetUrls} a link needs: a button that downloads a brochure has to say what
 * the saved file is called, and the key a library stores files under is a hash nobody wants in
 * their downloads folder. Implemented by the module that has the files — `webx-ui/module-media` —
 * and bound optionally, so a panel without a file manager offers no file links at all.
 *
 * A key the library no longer knows answers `null`: the link to it prints nothing rather than an
 * address that answers 404.
 */
interface AssetDownloads
{
    /**
     * @param  list<string>  $paths
     * @return array<string, array{url: string, name: string}|null>
     */
    public function downloads(array $paths): array;
}
