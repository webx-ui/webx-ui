<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One run of the audit: what it was aimed at, how far it has got, and what it counted.
 *
 * @property int $id
 * @property string $status
 * @property string $scope
 * @property string $base_url
 * @property string|null $resolve_to
 * @property int $pages_limit
 * @property int $pages_crawled
 * @property array<string, mixed>|null $progress
 * @property array<string, mixed>|null $counts
 * @property array<string, mixed>|null $probes
 * @property string|null $started_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error
 * @property Carbon|null $created_at
 */
class AuditRun extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    /** Everything a run can be: the probes, the config and the database — then the crawl. */
    public const FULL = 'full';

    /** The probes, the config and the database: seconds, and what a deploy runs. */
    public const QUICK = 'quick';

    public const SCOPES = [self::FULL, self::QUICK];

    protected $table = 'audit_runs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pages_limit' => 'integer',
            'pages_crawled' => 'integer',
            'progress' => 'array',
            'counts' => 'array',
            'probes' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return HasMany<AuditIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(AuditIssue::class, 'run_id');
    }

    /** @return HasMany<ContentUrl, $this> */
    public function contentUrls(): HasMany
    {
        return $this->hasMany(ContentUrl::class, 'run_id');
    }

    public function active(): bool
    {
        return in_array($this->status, [self::QUEUED, self::RUNNING], true);
    }

    /** The finished run of the same scope before this one — what "new" and "fixed" are against. */
    public function previous(): ?self
    {
        /** @var self|null $previous */
        $previous = self::query()
            ->where('status', self::DONE)
            ->where('scope', $this->scope)
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->first();

        return $previous;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::QUEUED, self::RUNNING]);
    }
}
