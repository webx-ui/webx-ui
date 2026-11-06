<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One build of a feed: the line of its journal (§7.6 of the feeds spec).
 *
 * @property int $id
 * @property int $feed_id
 * @property string $status
 * @property string $trigger
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int $selected
 * @property int $written
 * @property int $filtered
 * @property int $excluded
 * @property array<string, int>|null $rejected
 * @property array<string, int>|null $warnings
 * @property string|null $error
 * @property string|null $path
 * @property int|null $bytes
 * @property string|null $etag
 * @property string|null $content_type
 * @property Carbon|null $content_at
 * @property int|null $cursor
 * @property int|null $body_bytes
 * @property string|null $generation
 * @property array{categories?: list<int>}|null $state
 * @property Carbon|null $progress_at
 * @property-read Feed|null $feed
 */
class FeedBuild extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const MANUAL = 'manual';

    public const SCHEDULE = 'schedule';

    public const CHANGE = 'change';

    public const MCP = 'mcp';

    public $timestamps = false;

    protected $table = 'catalog_feed_builds';

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'content_at' => 'datetime',
            'progress_at' => 'datetime',
            'rejected' => 'array',
            'warnings' => 'array',
            'state' => 'array',
        ];
    }

    /** Whether nothing more will happen to it. */
    public function finished(): bool
    {
        return $this->status === self::DONE || $this->status === self::FAILED;
    }

    /**
     * @return BelongsTo<Feed, $this>
     */
    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class, 'feed_id');
    }
}
