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
 * @property array<string, mixed> $meta
 * @property Carbon|null $notified_at
 * @property string|null $notify_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Submission extends Model implements Notable
{
    use HasNotes;

    public const SOURCE_WEB = 'web';

    public const SOURCE_PANEL = 'panel';

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
    public function log(string $type, ?string $from = null, ?string $to = null, ?int $adminId = null): SubmissionEvent
    {
        return $this->events()->create([
            'admin_id' => $adminId,
            'type' => $type,
            'from' => $from,
            'to' => $to,
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
