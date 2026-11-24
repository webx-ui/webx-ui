<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The last heartbeat of one watcher (§14.3): what it runs, whether it is paused and why it waits.
 *
 * @property int $id
 * @property string $runner
 * @property array<string, mixed> $payload
 * @property Carbon $beat_at
 * @property Carbon|null $silent_notified_at
 */
class RunnerBeat extends Model
{
    protected $table = 'tasks_runner_beats';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'beat_at' => 'datetime',
            'silent_notified_at' => 'datetime',
        ];
    }
}
