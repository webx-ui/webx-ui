<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WebxUi\Media\Images\MissingSource;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

/**
 * The picture itself, served by the panel rather than by the disk.
 *
 * Everything that only looks at a picture uses its public address — that is the whole point of
 * putting a CDN in front of the library. The editor cannot: it draws the picture onto a canvas
 * and writes that canvas out, which a browser refuses to do for bytes that arrived from another
 * origin without `Access-Control-Allow-Origin`. Asking every installation to configure CORS on
 * its bucket is not a fix — a private bucket cannot be given those headers for the panel at all,
 * and a signed URL still points somewhere else.
 *
 * So the one operation that needs the pixels back reads them from here, same origin, behind the
 * same permission as the listing. It is an editing action, not page traffic: one request per
 * picture somebody actually opens.
 */
final class SourceController
{
    public function __construct(private readonly FileStore $files) {}

    public function __invoke(Request $request, MediaFile $file): StreamedResponse|JsonResponse
    {
        if (! $file->isImage()) {
            return new JsonResponse(['message' => __('webx-media::errors.not-an-image')], 404);
        }

        $disk = $this->files->disk($file->disk);

        if (! $disk->exists($file->path)) {
            throw new MissingSource($file->path);
        }

        return $disk->response($file->path, $file->file_name, [
            'Content-Type' => $file->mime,
            // The bytes at this key change when the picture is edited, and the address carries
            // no version — so it is the one address in the module that must not be kept.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
