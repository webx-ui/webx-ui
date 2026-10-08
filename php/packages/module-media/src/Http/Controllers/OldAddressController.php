<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use WebxUi\Media\Models\MediaAlias;
use WebxUi\Media\Storage\FileUrls;

/**
 * The public address of a key a file no longer has — `/storage/media/9f/2a/<uuid>.jpg` after the
 * picture became a WebP — answered with a 301 to where it is now.
 *
 * Reached only when the web server finds no file there and hands the request to the
 * application, which is what the usual `try_files` and Laravel's `.htaccess` do. Permanent,
 * because it is: a search engine moves its index over, and a CDN stops asking.
 */
final class OldAddressController
{
    public function __invoke(string $key, FileUrls $urls): RedirectResponse
    {
        $prefix = trim((string) config('webx-media.prefix', 'media'), '/');
        $file = MediaAlias::query()->where('path', $prefix.'/'.$key)->first()?->file;

        abort_if($file === null, 404);

        return redirect()->away($urls->url($file), 301);
    }
}
