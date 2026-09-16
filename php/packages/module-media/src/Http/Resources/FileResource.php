<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Media\Support\MediaType;

/**
 * @mixin MediaFile
 */
final class FileResource extends JsonResource
{
    private bool $duplicate = false;

    /** Said out loud so the panel can tell the person their file was already here. */
    public function duplicate(bool $duplicate = true): self
    {
        $this->duplicate = $duplicate;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MediaFile $file */
        $file = $this->resource;
        $urls = app(FileUrls::class);

        return [
            'id' => $file->id,
            'directory_id' => $file->directory_id,
            'name' => $file->name,
            'file_name' => $file->file_name,
            'extension' => $file->extension,
            'mime' => $file->mime,
            'type' => MediaType::of($file->mime),
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
            // What an entity stores. The address beside it is worked out on every read, so the
            // library can change disks without touching anything that points at a file.
            'path' => $file->path,
            'url' => $urls->url($file),
            'thumb' => $urls->thumbUrl($file),
            // Where the editor reads the picture from. Same origin as the panel on purpose:
            // it draws onto a canvas and writes that canvas out, which a browser refuses for
            // bytes fetched from a CDN that sends no CORS headers — and a private bucket
            // cannot be given any.
            'source' => $file->isImage()
                ? route('webx.media.files.source', [
                    'file' => $file->id,
                    'v' => substr($file->hash, 0, 8),
                ])
                : null,
            'editable' => $file->isImage(),
            'has_original' => $file->hasOriginal(),
            'duplicate' => $this->duplicate,
            'created_at' => $file->created_at?->toAtomString(),
        ];
    }
}
