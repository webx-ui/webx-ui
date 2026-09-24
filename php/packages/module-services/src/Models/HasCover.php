<?php

declare(strict_types=1);

namespace WebxUi\Services\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Storage\FileUrls;

/**
 * A picture, held by id rather than by address.
 *
 * The address is never stored: a private disk hands out a signed link that expires within the
 * hour, and the image editor writes over the same key, so a saved `src` is either dead or the
 * picture as it was before somebody cropped it. `FileUrls` works it out on every read.
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

    /**
     * The cover as `wx-media` edits it: a library key, never an address.
     *
     * @return array{path: string}|null
     */
    public function coverValue(): ?array
    {
        return $this->cover instanceof MediaFile ? ['path' => $this->cover->path] : null;
    }

    /** What `wx-media` sent, as the id of the row it names — or null for none, or a key nobody has. */
    public static function coverIdOf(mixed $value): ?int
    {
        $path = is_array($value) ? $value['path'] ?? null : null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $file = Container::getInstance()->make(MediaFiles::class)->find($path);

        return $file === null ? null : (int) $file->getKey();
    }
}
