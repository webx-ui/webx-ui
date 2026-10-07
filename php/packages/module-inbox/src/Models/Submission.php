<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Models;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Notes\HasNotes;
use WebxUi\Admin\Notes\Notable;
use WebxUi\Admin\Notes\Note;
use WebxUi\Auth\Models\CmsUser;

/**
 * One thing somebody sent.
 *
 * @property int $id
 * @property int $form_id
 * @property int $status_id
 * @property int|null $assignee_id
 * @property string $hash
 * @property Carbon|null $read_at
 * @property string $source
 * @property string|null $placement
 * @property array<string, mixed> $meta
 * @property Carbon|null $notified_at
 * @property string|null $notify_error
 * @property Carbon|null $notify_queued_at
 * @property list<array{address: string, state: string, error: string|null, at: string}>|null $notify_recipients
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Submission extends Model implements Notable
{
    use HasNotes;

    public const SOURCE_WEB = 'web';

    public const SOURCE_PANEL = 'panel';

    /** Nobody was written to: the form names nobody, or nobody it names is left. */
    public const NOTIFY_NONE = 'none';

    /** A letter is waiting for a queue worker, and none has failed. */
    public const NOTIFY_QUEUED = 'queued';

    /** Every letter left. */
    public const NOTIFY_DELIVERED = 'delivered';

    /** A letter could not be sent; `notify_error` says why. */
    public const NOTIFY_FAILED = 'failed';

    /**
     * What a submission is called in the morph map, and therefore in the address of its
     * notes. An alias and not a class name: `WebxUi\Inbox\Models\Submission` in a database
     * column is a namespace nobody is allowed to rename afterwards.
     */
    public const MORPH = 'inbox_submission';

    protected $table = 'inbox_submissions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'read_at' => 'datetime',
            'notified_at' => 'datetime',
            'notify_queued_at' => 'datetime',
            'notify_recipients' => 'array',
        ];
    }

    /**
     * How the notification went, in one word and the detail behind it (§9).
     *
     * Read off the columns rather than stored as a word of its own, so a submission written
     * before the queue was watched reads as it did: `notified_at` without an error is
     * delivered, an error is failed. A failure wins over letters still waiting — the one thing
     * an administrator has to act on is said first.
     *
     * @return array{state: string, error: string|null, queued_at: string|null, delivered_at: string|null, recipients: list<array{address: string, state: string, error: string|null, at: string}>}
     */
    public function notification(): array
    {
        $state = match (true) {
            $this->notify_error !== null => self::NOTIFY_FAILED,
            $this->notify_queued_at !== null => self::NOTIFY_QUEUED,
            $this->notified_at !== null => self::NOTIFY_DELIVERED,
            default => self::NOTIFY_NONE,
        };

        return [
            'state' => $state,
            'error' => $this->notify_error,
            'queued_at' => $this->notify_queued_at?->toAtomString(),
            'delivered_at' => $this->notified_at?->toAtomString(),
            'recipients' => array_values($this->notify_recipients ?? []),
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    /**
     * @return BelongsTo<Status, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    /**
     * @return BelongsTo<CmsUser, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(CmsUser::class, 'assignee_id');
    }

    /**
     * @return HasMany<SubmissionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(SubmissionValue::class, 'submission_id')->orderBy('id');
    }

    /**
     * @return HasMany<SubmissionFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class, 'submission_id')->orderBy('id');
    }

    /**
     * @return HasMany<SubmissionEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SubmissionEvent::class, 'submission_id')->orderBy('id');
    }

    /**
     * Which permission the notes on a submission are behind (§13).
     *
     * `inbox.update` rather than `inbox.view`: a note is part of dealing with a submission,
     * the same as its status and its assignee, and somebody who may only read the list has
     * nothing to add to the conversation about it.
     */
    public function notesPermission(): string
    {
        return 'inbox.update';
    }

    /** A note is something that happened to the submission, so the log says so. */
    protected function noteAdded(Note $note): void
    {
        $this->log(SubmissionEvent::NOTE, null, null, $note->admin_id);
    }

    /** The answer to one field, by the machine name it was given under. */
    public function value(string $name): ?SubmissionValue
    {
        return $this->values->firstWhere('name', $name);
    }

    /**
     * Read once, by whoever got there first (§2.16). A panel is one or two pairs of hands, and
     * per-administrator unread would be a join table and then an argument about whose badge
     * the count belongs to.
     */
    public function markRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill(['read_at' => Carbon::now()])->save();
    }

    /**
     * One line of the log. It is only ever added to: an audit trail that can be edited is a
     * story rather than a record.
     */
    public function log(string $type, ?string $from = null, ?string $to = null, ?int $adminId = null, ?string $field = null): SubmissionEvent
    {
        return $this->events()->create([
            'admin_id' => $adminId,
            'type' => $type,
            'field' => $field,
            // The columns hold 255, and a corrected letter is longer than that.
            'from' => $from === null ? null : mb_substr($from, 0, 255),
            'to' => $to === null ? null : mb_substr($to, 0, 255),
            'created_at' => Carbon::now(),
        ]);
    }

    protected static function booted(): void
    {
        // The rows below cascade in the database, which raises no model events — so the bytes
        // would stay on the disk with nothing left pointing at them (§8).
        static::deleting(function (Submission $submission): void {
            $filesystems = app(FilesystemFactory::class);

            foreach ($submission->files as $file) {
                $filesystems->disk($file->disk)->delete($file->path);
            }
        });
    }
}
