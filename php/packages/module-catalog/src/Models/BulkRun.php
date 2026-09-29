<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One bulk action over more products than a request should hold (§11.4): the ids fixed when it
 * started, how far the chunks have got, and what refused.
 *
 * A run of `sync_limit` products or fewer is done inside the request and never becomes a row —
 * the same shape is answered with `id` null, and the journal has its run either way.
 *
 * @property int $id
 * @property int|null $admin_id
 * @property string $admin_name
 * @property string $action
 * @property array<string, mixed>|null $params
 * @property array<string, mixed>|null $selection
 * @property int $total
 * @property int $done
 * @property int $failed
 * @property list<array{id: int, name: string, message: string}>|null $errors
 * @property int $cursor
 * @property int|null $history_id
 * @property string $status
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BulkRun extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    /** The first hundred refusals are kept, by product; the counter keeps the rest. */
    public const ERRORS_KEPT = 100;

    protected $table = 'catalog_bulk_runs';

    protected $guarded = ['id'];

    protected $attributes = [
        'admin_name' => '',
        'total' => 0,
        'done' => 0,
        'failed' => 0,
        'cursor' => 0,
        'status' => self::QUEUED,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admin_id' => 'integer',
            'params' => 'array',
            'selection' => 'array',
            'total' => 'integer',
            'done' => 'integer',
            'failed' => 'integer',
            'errors' => 'array',
            'cursor' => 'integer',
            'history_id' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    public function finished(): bool
    {
        return in_array($this->status, [self::DONE, self::FAILED], true);
    }

    /**
     * What the panel polls and an agent is answered with.
     *
     * @return array<string, mixed>
     */
    public function toResponse(string $label): array
    {
        return [
            'id' => $this->exists ? $this->id : null,
            'action' => $this->action,
            'label' => $label,
            'status' => $this->status,
            'total' => $this->total,
            'done' => $this->done,
            'failed' => $this->failed,
            'errors' => $this->errors ?? [],
            'history_id' => $this->history_id,
            'created_at' => $this->created_at?->toAtomString(),
            'finished_at' => $this->finished_at?->toAtomString(),
        ];
    }
}
