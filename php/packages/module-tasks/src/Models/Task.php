<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Tasks\Workflow\Priority;
use WebxUi\Tasks\Workflow\ReviewMode;
use WebxUi\Tasks\Workflow\StageStatus;
use WebxUi\Tasks\Workflow\Tasks;
use WebxUi\Tasks\Workflow\TaskStatus;

/**
 * A goal with its specification, its acceptance criteria and the stages that reach it.
 *
 * Changed only through {@see Tasks}: the status is derived from the stages and written there,
 * and a save from anywhere else would skip the journal.
 *
 * @property int $id
 * @property int $project_id
 * @property int $number
 * @property string $title
 * @property TaskStatus $status
 * @property Priority $priority
 * @property string|null $spec
 * @property string|null $acceptance
 * @property string|null $branch
 * @property int|null $created_by
 * @property Carbon|null $closed_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Collection<int, Stage> $stages
 */
class Task extends Model
{
    use SoftDeletes;

    protected $table = 'tasks_tasks';

    /** @var list<string> */
    protected $fillable = ['project_id', 'number', 'title', 'status', 'priority', 'spec', 'acceptance', 'branch', 'created_by', 'closed_at'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'priority' => 'normal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * @return HasMany<Stage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class, 'task_id')->orderBy('position');
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journal(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'task_id')->orderBy('id');
    }

    /**
     * @return BelongsTo<CmsUser, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(CmsUser::class, 'created_by');
    }

    /**
     * Tasks a person has to act on: a stage reported for a person's review, or a stage whose agent
     * asked a question. The panel's «waiting for me» and the badge on its menu entry.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeWaitingForPerson(Builder $query): void
    {
        $query->whereHas('stages', static fn (Builder $stages) => $stages->where(static fn (Builder $either) => $either
            ->where(static fn (Builder $review) => $review
                ->where('status', StageStatus::Review->value)
                ->where('review_mode', ReviewMode::Human->value))
            ->orWhere('status', StageStatus::Blocked->value)));
    }
}
