<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use WebxUi\Tasks\Workflow\ActorType;
use WebxUi\Tasks\Workflow\JournalKind;

/**
 * One line of what happened to a task.
 *
 * Append-only, and enforced rather than promised: saving a line that exists, or deleting one,
 * throws. The journal is what a person reads to learn who accepted what and why it was sent
 * back; a line that can be corrected afterwards proves nothing. A query builder can still reach
 * the table — that is the database's business, not an editor's.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $stage_id
 * @property ActorType $actor_type
 * @property string $actor_name
 * @property JournalKind $kind
 * @property string|null $body
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property-read Stage|null $stage
 */
class JournalEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tasks_journal';

    /** @var list<string> */
    protected $fillable = ['task_id', 'stage_id', 'actor_type', 'actor_name', 'kind', 'body', 'meta'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('A journal entry is never changed: write a new one.');
        });

        static::deleting(static function (): never {
            throw new LogicException('A journal entry is never deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'kind' => JournalKind::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }
}
