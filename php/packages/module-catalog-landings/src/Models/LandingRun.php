<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One generation of landings handed to the queue (§8.3 of the landings spec). A generation of
 * `generate.sync_limit` rows or fewer is done inside the request and never becomes a row — the
 * same shape is answered with `id` null.
 *
 * `skipped` counts the rows the preview already refused (a slug or a set taken, no products);
 * `failed` — the ones a save refused on the way, because something changed since the preview.
 *
 * @property int $id
 * @property int|null $admin_id
 * @property string $admin_name
 * @property list<array<string, mixed>> $rows
 * @property int $total
 * @property int $done
 * @property int $skipped
 * @property int $failed
 * @property list<array{slug: string, name: string, message: string}>|null $errors
 * @property int $cursor
 * @property string $status
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LandingRun extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    /** The first hundred refusals are kept; the counter keeps the rest. */
    public const ERRORS_KEPT = 100;

    protected $table = 'catalog_landing_runs';

    protected $guarded = ['id'];

    protected $attributes = [
        'admin_name' => '',
        'total' => 0,
        'done' => 0,
        'skipped' => 0,
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
            'rows' => 'array',
            'total' => 'integer',
            'done' => 'integer',
            'skipped' => 'integer',
            'failed' => 'integer',
            'errors' => 'array',
            'cursor' => 'integer',
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
    public function toResponse(): array
    {
        return [
            'id' => $this->exists ? $this->id : null,
            'status' => $this->status,
            'total' => $this->total,
            'done' => $this->done,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'errors' => $this->errors ?? [],
            'created_at' => $this->created_at?->toAtomString(),
            'finished_at' => $this->finished_at?->toAtomString(),
        ];
    }
}
