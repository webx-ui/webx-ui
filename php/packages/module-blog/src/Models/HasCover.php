<?php

declare(strict_types=1);

namespace WebxUi\Blog\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;

/**
 * A picture at the top, held by id rather than by address.
 *
 * The address is never stored, and the reason is not only that it differs between deployments:
 * a private disk hands out a signed link that expires within the hour, and the image editor
 * writes over the same key, so a saved `src` is either dead or is the picture as it was before
 * somebody cropped it. `FileUrls` works it out each time from the file itself, which is the
 * same rule `wx-media` has always followed.
 *
 * @phpstan-require-extends Model
 */
trait HasCover
{
    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_id');
    }

    /** What a template puts in `src`, or null when there is no cover. */
    public function coverUrl(): ?string
    {
        $cover = $this->cover;

        return $cover instanceof MediaFile
            ? Container::getInstance()->make(FileUrls::class)->url($cover)
            : null;
    }
}
