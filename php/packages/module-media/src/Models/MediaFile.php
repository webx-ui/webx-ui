<?php

declare(strict_types=1);

namespace WebxUi\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One file in the library.
 *
 * `path` is fixed at upload and never changes again: renaming touches `name`, moving touches
 * `directory_id`, and editing an image writes over the same key. That is what keeps an address
 * already written into an article working after any of the three.
 *
 * @property int $id
 * @property int $directory_id
 * @property string $disk
 * @property string $path
 * @property string|null $original_path
 * @property string $hash
 * @property string $name
 * @property string $file_name
 * @property string $extension
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaFile extends Model
{
    protected $fillable = [
        'directory_id',
        'disk',
        'path',
        'original_path',
        'hash',
        'name',
        'file_name',
        'extension',
        'mime',
        'size',
        'width',
        'height',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The bytes go with the row. Deleting a folder goes through the service that collects
        // its files and deletes them one by one for exactly this reason: the database's own
        // cascade raises no events, and the files would stay on the disk forever.
        static::deleted(static function (self $file): void {
            $file->eraseFiles();
        });
    }

    /**
     * @return BelongsTo<MediaDirectory, $this>
     */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(MediaDirectory::class, 'directory_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function hasOriginal(): bool
    {
        return $this->original_path !== null;
    }

    /**
     * @param  Builder<MediaFile>  $query
     * @return Builder<MediaFile>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
    }

    /** Remove what this row owns on the disk: the file itself and the copy kept before editing. */
    public function eraseFiles(): void
    {
        $disk = Storage::disk($this->disk);

        $disk->delete(array_values(array_filter([$this->path, $this->original_path])));
    }
}
