<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One line of the journal: a save, or the run a batch of saves was made in.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $subject_type
 * @property int|null $subject_id
 * @property string $event
 * @property string $source
 * @property int|null $admin_id
 * @property string $admin_name
 * @property int|null $grant_id
 * @property list<array<string, mixed>>|null $changes
 * @property array<string, mixed>|null $summary
 * @property Carbon|null $created_at
 */
final class HistoryEntry extends Model
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const RESTORED = 'restored';

    public const PUBLISHED = 'published';

    public const UNPUBLISHED = 'unpublished';

    /** The parent row of an import or a bulk action. */
    public const RUN = 'run';

    public const EVENTS = [
        self::CREATED, self::UPDATED, self::DELETED, self::RESTORED,
        self::PUBLISHED, self::UNPUBLISHED, self::RUN,
    ];

    // A journal is written, never edited: there is no `updated_at` to keep.
    public const UPDATED_AT = null;

    protected $table = 'cms_history';

    protected $fillable = [
        'parent_id', 'subject_type', 'subject_id', 'event', 'source',
        'admin_id', 'admin_name', 'grant_id', 'changes', 'summary', 'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'subject_id' => 'integer',
            'admin_id' => 'integer',
            'grant_id' => 'integer',
            'changes' => 'array',
            'summary' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<self, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isRun(): bool
    {
        return $this->event === self::RUN;
    }
}
