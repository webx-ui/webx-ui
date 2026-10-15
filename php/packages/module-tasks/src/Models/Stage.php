<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Tasks\Workflow\ReviewMode;
use WebxUi\Tasks\Workflow\StageStatus;

/**
 * A piece of a task that is finished and checked on its own — one agent session's work.
 *
 * @property int $id
 * @property int $task_id
 * @property int $position
 * @property string $title
 * @property string|null $brief
 * @property string|null $acceptance
 * @property ReviewMode $review_mode
 * @property StageStatus $status
 * @property string|null $assignee
 * @property int $attempt
 * @property Carbon|null $claimed_at
 * @property Carbon|null $reported_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $stale_notified_at
 * @property string|null $report
 * @property string|null $result_ref
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Task $task
 */
class Stage extends Model
{
    protected $table = 'tasks_stages';

    /** @var list<string> */
    protected $fillable = [
        'task_id', 'position', 'title', 'brief', 'acceptance', 'review_mode', 'status', 'assignee', 'attempt',
        'claimed_at', 'reported_at', 'accepted_at', 'status_changed_at', 'stale_notified_at', 'report', 'result_ref',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'review_mode' => 'auto',
        'attempt' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'attempt' => 'integer',
            'review_mode' => ReviewMode::class,
            'status' => StageStatus::class,
            'claimed_at' => 'datetime',
            'reported_at' => 'datetime',
            'accepted_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'stale_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id')->withTrashed();
    }
}
