<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Auth\Models\CmsUser;

/**
 * One line of what happened to a submission.
 *
 * Append-only: no update, no delete. `from` and `to` hold whatever the change was about — the
 * key of a status, the name of an administrator — as text and not as a foreign key, so that a
 * deleted role or a renamed status does not rewrite history.
 *
 * @property int $id
 * @property int $submission_id
 * @property int|null $admin_id
 * @property string $type
 * @property string|null $from
 * @property string|null $to
 * @property Carbon|null $created_at
 */
class SubmissionEvent extends Model
{
    public const UPDATED_AT = null;

    public const CREATED = 'created';

    public const STATUS = 'status';

    public const ASSIGNEE = 'assignee';

    public const NOTE = 'note';

    public const NOTIFIED = 'notified';

    protected $table = 'inbox_submission_events';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    /**
     * Null is the system: the submission arriving, the notification going out.
     *
     * @return BelongsTo<CmsUser, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(CmsUser::class, 'admin_id');
    }
}
