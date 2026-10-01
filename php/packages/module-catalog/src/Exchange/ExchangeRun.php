<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * One import or export (§6 of the exchange spec): what it was asked to do — a snapshot of the
 * profile, which may change after — how far it has got, and how it ended.
 *
 * @property int $id
 * @property int|null $profile_id
 * @property string $direction
 * @property string $format
 * @property array<string, mixed>|null $options
 * @property array<array-key, mixed>|null $mapping
 * @property bool $dry_run
 * @property string $status
 * @property string|null $source
 * @property string|null $file
 * @property int $rows_total
 * @property int $rows_done
 * @property int $created
 * @property int $updated
 * @property int $skipped
 * @property int $failed
 * @property int $absent
 * @property int|null $history_id
 * @property int|null $admin_id
 * @property string $admin_name
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExchangeRun extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const STOPPED = 'stopped';

    public const FAILED = 'failed';

    protected $table = 'catalog_exchange_runs';

    protected $guarded = ['id'];

    protected $attributes = [
        'admin_name' => '',
        'dry_run' => false,
        'status' => self::QUEUED,
        'rows_total' => 0,
        'rows_done' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'absent' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile_id' => 'integer',
            'options' => 'array',
            'mapping' => 'array',
            'dry_run' => 'boolean',
            'rows_total' => 'integer',
            'rows_done' => 'integer',
            'created' => 'integer',
            'updated' => 'integer',
            'skipped' => 'integer',
            'failed' => 'integer',
            'absent' => 'integer',
            'history_id' => 'integer',
            'admin_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function finished(): bool
    {
        return in_array($this->status, [self::DONE, self::STOPPED, self::FAILED], true);
    }

    public function isImport(): bool
    {
        return $this->direction === ExchangeProfile::IMPORT;
    }

    /** An option of the run, as it was when it started. */
    public function option(string $key, mixed $default = null): mixed
    {
        return ($this->options ?? [])[$key] ?? $default;
    }

    /**
     * What the panel polls and an agent is answered with.
     *
     * @return array<string, mixed>
     */
    public function toResponse(): array
    {
        $options = $this->options ?? [];
        // What the run knew of its starter's permissions is its own business.
        unset($options['granted']);

        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'direction' => $this->direction,
            'format' => $this->format,
            'options' => $options,
            'mapping' => $this->mapping ?? [],
            'dry_run' => $this->dry_run,
            'status' => $this->status,
            'source' => $this->source,
            'rows_total' => $this->rows_total,
            'rows_done' => $this->rows_done,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'absent' => $this->absent,
            'history_id' => $this->history_id,
            'admin_id' => $this->admin_id,
            'admin_name' => $this->admin_name,
            'file_url' => $this->fileUrl(),
            'started_at' => $this->started_at?->toAtomString(),
            'finished_at' => $this->finished_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }

    /** What the finished export is called when it is downloaded. */
    public function fileName(): string
    {
        return 'catalog-export-'.$this->id.'.'.pathinfo((string) $this->file, PATHINFO_EXTENSION);
    }

    /**
     * The finished export, by a signed address that lives as long as the file: a link an agent
     * can hand on without a session behind it.
     */
    public function fileUrl(): ?string
    {
        if ($this->isImport() || $this->status !== self::DONE || $this->file === null || $this->finished_at === null) {
            return null;
        }

        $until = $this->finished_at->copy()->addHours(max(1, (int) config('webx-catalog.exchange.keep_hours', 24)));

        return $until->isPast() ? null : URL::temporarySignedRoute('webx.catalog.exchange.download', $until, ['run' => $this->id, 'name' => $this->fileName()]);
    }
}
