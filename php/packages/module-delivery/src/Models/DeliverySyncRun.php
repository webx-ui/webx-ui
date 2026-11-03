<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Trigger;

/**
 * A run of a carrier's directory (§5): where it is (the cursor of the next page), what it did
 * and how it ended. The panel, MCP and `webx:doctor` read the last success of each directory.
 *
 * @property int $id
 * @property string $carrier
 * @property Directory $directory
 * @property RunStatus $status
 * @property Trigger $trigger
 * @property string|null $cursor
 * @property int $pages
 * @property int $seen
 * @property int $created
 * @property int $updated
 * @property int $removed
 * @property int $queued
 * @property string|null $error
 * @property array<string, mixed>|null $warnings
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class DeliverySyncRun extends Model
{
    protected $table = 'delivery_sync_runs';

    public $timestamps = false;

    protected $fillable = [
        'carrier', 'directory', 'status', 'trigger', 'cursor', 'pages', 'seen', 'created', 'updated',
        'removed', 'queued', 'error', 'warnings', 'started_at', 'finished_at',
    ];

    protected $attributes = [
        'pages' => 0,
        'seen' => 0,
        'created' => 0,
        'updated' => 0,
        'removed' => 0,
        'queued' => 0,
    ];

    protected function casts(): array
    {
        return [
            'directory' => Directory::class,
            'status' => RunStatus::class,
            'trigger' => Trigger::class,
            'pages' => 'integer',
            'seen' => 'integer',
            'created' => 'integer',
            'updated' => 'integer',
            'removed' => 'integer',
            'queued' => 'integer',
            'warnings' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOf(Builder $query, string $carrier, Directory $directory): Builder
    {
        return $query->where('carrier', $carrier)->where('directory', $directory->value);
    }

    public function isRunning(): bool
    {
        return $this->status === RunStatus::Running;
    }

    /** How many records the held pass would remove — the number the panel says (§7.5). */
    public function held(): int
    {
        return (int) ($this->warnings['held'] ?? 0);
    }
}
