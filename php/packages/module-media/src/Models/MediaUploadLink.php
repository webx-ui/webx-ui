<?php

declare(strict_types=1);

namespace WebxUi\Media\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time link an agent uploads a file through (`media_upload_link`).
 *
 * The signature on the link says it is ours and when it ends; the row says what the signature
 * cannot: whether the link has been used, which folder it fills, and who asked for it — so that
 * the file is put in by that administrator, and the row stays as the record that they did.
 *
 * @property string $id
 * @property int $directory_id
 * @property int|null $admin_id
 * @property int|null $grant_id
 * @property string|null $name
 * @property array<string, string>|string|null $alt
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int|null $file_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaDirectory|null $directory
 * @property-read MediaFile|null $file
 */
class MediaUploadLink extends Model
{
    use HasUuids;

    protected $table = 'media_upload_links';

    protected $fillable = ['directory_id', 'admin_id', 'grant_id', 'name', 'alt', 'expires_at', 'used_at', 'file_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alt' => 'json',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MediaDirectory, $this>
     */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(MediaDirectory::class, 'directory_id');
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'file_id');
    }
}
