<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recipient of one channel for one event (§9 of the notifications spec): what was sent,
 * whether it went, and why not.
 *
 * The values of the event are kept, the message is not: a retry renders it again, so a template
 * fixed after a failure sends the fixed text.
 *
 * @property int $id
 * @property int|null $channel_id
 * @property string $channel_name
 * @property string $provider
 * @property string $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $locale
 * @property string $recipient
 * @property string|null $recipient_label
 * @property string $status
 * @property string|null $reason
 * @property string|null $error
 * @property int $attempts
 * @property bool $is_test
 * @property bool $truncated
 * @property list<string>|null $unknown_variables
 * @property array<string, string>|null $variables
 * @property Carbon|null $created_at
 * @property Carbon|null $sent_at
 */
class Delivery extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const STATUSES = [self::QUEUED, self::SENT, self::FAILED, self::SKIPPED];

    /** A queued row older than this is stuck: no worker has taken it (§13). */
    public const STUCK_MINUTES = 15;

    public const UPDATED_AT = null;

    protected $table = 'notifications_deliveries';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'attempts' => 0,
        'is_test' => false,
        'truncated' => false,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'channel_id' => 'integer',
        'subject_id' => 'integer',
        'attempts' => 'integer',
        'is_test' => 'boolean',
        'truncated' => 'boolean',
        'unknown_variables' => 'array',
        'variables' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class)->withTrashed();
    }

    /** Failed, or queued so long ago that no worker is coming for it (§8.4). */
    public function retryable(): bool
    {
        return $this->status === self::FAILED
            || ($this->status === self::QUEUED && $this->created_at !== null && $this->created_at->lt(Carbon::now()->subMinutes(self::STUCK_MINUTES)));
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $this->variables ?? []);
    }
}
