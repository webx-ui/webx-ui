<?php

declare(strict_types=1);

namespace WebxUi\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A key a file used to have.
 *
 * A file's key changes in one case only — converted to WebP, `<uuid>.jpg` becomes `<uuid>.webp`
 * — and everything the site keeps is rewritten to the new one at that moment. The old one is
 * remembered all the same, for what the rewrite cannot reach: an address in a search engine, a
 * CDN, an e-mail, a config file. The old public address answers with a 301 to the new one, and
 * `files/by-path` finds the file by either.
 *
 * @property int $id
 * @property string $path
 * @property int $file_id
 * @property Carbon|null $created_at
 * @property-read MediaFile|null $file
 */
class MediaAlias extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'media_aliases';

    protected $fillable = ['path', 'file_id'];

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'file_id');
    }

    /** The file a key now belongs to, the current key or one it had. */
    public static function resolve(string $path): ?MediaFile
    {
        return MediaFile::query()->where('path', $path)->first()
            ?? self::query()->where('path', $path)->first()?->file;
    }
}
