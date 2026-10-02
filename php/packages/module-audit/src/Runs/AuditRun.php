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

    /**
     * A few addresses, crawled and checked page by page (§3): after an edit, "recheck" on the
     * card. No probes, no database, no link is followed — and the checks that judge the whole
     * site (duplicates, orphans, depth, the sitemap) do not run on five pages.
     */
    public const URLS = 'urls';

    public const SCOPES = [self::FULL, self::QUICK, self::URLS];

    /** At most this many addresses in a run of the `urls` scope. */
    public const URLS_LIMIT = 50;

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

    /**
     * The finished run of the same scope before this one — what "new" and "fixed" are against.
     * A recheck of a few addresses is measured against the last full run: that is the one whose
     * findings the edit was meant to close.
     */
    public function previous(): ?self
    {
        /** @var self|null $previous */
        $previous = self::query()
            ->where('status', self::DONE)
            ->where('scope', $this->scope === self::URLS ? self::FULL : $this->scope)
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->first();

        return $previous;
    }

    /**
     * The addresses of a `urls` run, as given.
     *
     * @return list<string>
     */
    public function urls(): array
    {
        return array_values(array_filter((array) ($this->progress['urls'] ?? []), 'is_string'));
    }

    /**
     * The paths the crawl leaves out, as they were when the run started.
     *
     * @return list<string>
     */
    public function excluded(): array
    {
        return array_values(array_filter((array) ($this->progress['exclude'] ?? []), 'is_string'));
    }

    /**
     * Runs that speak for the whole site — not a recheck of a few addresses, which the overview,
     * the findings and the hosts must not take for "the last run".
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSiteWide(Builder $query): Builder
    {
        return $query->whereIn('scope', [self::FULL, self::QUICK]);
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
