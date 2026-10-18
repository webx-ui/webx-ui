<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use WebxUi\Admin\Access\AccessPolicy;

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
     * Whether this reader may have the run's file and its source as it was given: its starter
     * and a super administrator (§14.3 of the permissions spec, choice 11). `null` is the console
     * or the local agent server, which have no administrator and see everything.
     *
     * Everybody with the catalogue sees that the run happened. The file is another matter: its
     * columns were picked by the starter's permissions — notes, purchase prices — and a reader
     * with fewer would get through the run what the export screen would not give them.
     */
    public function yours(?Authenticatable $reader): bool
    {
        if ($reader === null) {
            return true;
        }

        if ($this->admin_id !== null && (string) $reader->getAuthIdentifier() === (string) $this->admin_id) {
            return true;
        }

        return app(AccessPolicy::class)->isSuper($reader);
    }

    /**
     * What the panel polls and an agent is answered with, for this reader (see {@see yours()}).
     *
     * @return array<string, mixed>
     */
    public function toResponse(?Authenticatable $reader = null): array
    {
        $yours = $this->yours($reader);
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
            'source' => $yours ? $this->source : self::withoutSecrets($this->source),
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
            'file_url' => $yours ? $this->fileUrl() : null,
            // The file is there, but not for this reader: the panel says whose it is rather than «gone».
            'file_yours' => $yours,
            'started_at' => $this->started_at?->toAtomString(),
            'finished_at' => $this->finished_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }

    /**
     * An address to import from, without what may be a key: the query, the fragment and the
     * user in front of the host. `https://feed.example/catalog.xml?token=…` is somebody's
     * access to a supplier, and the run list is read by everybody with the catalogue.
     */
    public static function withoutSecrets(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $parts = parse_url($source);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $source;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $hidden = isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']);

        return $parts['scheme'].'://'.$parts['host'].$port.($parts['path'] ?? '').($hidden ? '?…' : '');
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
