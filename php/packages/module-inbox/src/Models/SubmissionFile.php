<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Models;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something a visitor attached.
 *
 * It lives on the module's own disk, private by default, and nothing anywhere links to it: the
 * panel reads the bytes and serves them itself, behind `inbox.view` (§8). A signed address to a
 * bucket would be one somebody forwards, and it would outlive the permission that produced it.
 *
 * @property int $id
 * @property int $submission_id
 * @property int|null $field_id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property int $size
 * @property string|null $mime
 * @property Carbon|null $created_at
 */
class SubmissionFile extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'inbox_submission_files';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    /**
     * @return BelongsTo<Field, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'field_id')->withTrashed();
    }

    protected static function booted(): void
    {
        static::deleted(function (SubmissionFile $file): void {
            app(FilesystemFactory::class)->disk($file->disk)->delete($file->path);
        });
    }
}
