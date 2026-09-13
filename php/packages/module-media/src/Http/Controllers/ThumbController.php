<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use WebxUi\Media\Images\Thumbnails;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;

final class ThumbController
{
    public function __construct(
        private readonly Thumbnails $thumbnails,
        private readonly FileUrls $urls,
    ) {}

    /**
     * A redirect rather than a stream.
     *
     * The variant lives on the same disk as the picture, so once it exists the disk — or the CDN
     * in front of it — serves it, and PHP is not in the way of a grid of two hundred previews.
     */
    public function __invoke(Request $request, MediaFile $file): RedirectResponse|JsonResponse
    {
        if (! $file->isImage()) {
            return new JsonResponse(['message' => __('webx-media::errors.not-an-image')], 404);
        }

        /** @var array{w: int, h: int|null, fit: string} $parameters */
        $parameters = $request->validate([
            // A closed list: an open width would let one request ask the server to resize
            // anything to anything, as many times as it likes.
            'w' => ['required', 'integer', Rule::in((array) config('webx-media.thumbs.widths', []))],
            'h' => ['nullable', 'integer', Rule::in((array) config('webx-media.thumbs.widths', []))],
            'fit' => ['nullable', Rule::in((array) config('webx-media.thumbs.fits', []))],
        ]);

        $path = $this->thumbnails->variant(
            $file,
            (int) $parameters['w'],
            isset($parameters['h']) ? (int) $parameters['h'] : null,
            $parameters['fit'] ?? 'cover',
        );

        return new RedirectResponse($this->urls->variantUrl($file, $path));
    }
}
