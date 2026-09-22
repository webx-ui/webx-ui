<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Calls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Mcp\Grants\Grant;

/**
 * One tool call an agent made, answered or refused.
 *
 * The trail of what agents did through the panel, the way `cms_login_records` is the trail of
 * who signed in: who it acted as, on which connection, which tool, with what, and whether it
 * got an answer. There is deliberately no link to whatever the call was about — a page, a
 * file, a role — because there is no one kind of thing a call is about.
 *
 * @property int $id
 * @property int|null $cms_user_id
 * @property int|null $grant_id
 * @property string $tool
 * @property string|null $arguments
 * @property bool $dry_run
 * @property bool $ok
 * @property string|null $error
 * @property int $duration_ms
 * @property Carbon|null $created_at
 * @property-read Grant|null $grant
 */
class Call extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mcp_calls';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'ok' => 'boolean',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The connection the call came in on, for the client's name. Absent for a call on the
     * stdio server, and for a token that names no client.
     *
     * @return BelongsTo<Grant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class, 'grant_id');
    }

    /**
     * Rows older than the retention, the ones the nightly prune removes.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOlderThan(Builder $query, int $days): void
    {
        $query->where('created_at', '<', Carbon::now()->subDays($days));
    }
}
