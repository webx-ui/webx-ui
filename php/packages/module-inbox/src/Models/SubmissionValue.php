<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer, and beside it the question as it was asked at the time.
 *
 * `name`, `label` and `type` are a snapshot and not a join (§2.2): a submission from a year ago
 * reads with the words it was given, even if the field has since been renamed, retyped or
 * deleted. It is versioning of the schema for the price of three columns.
 *
 * `type` stays a plain string rather than becoming the enum — a snapshot has to survive a type
 * this package no longer has a name for.
 *
 * @property int $id
 * @property int $submission_id
 * @property int $form_id
 * @property int|null $field_id
 * @property string $name
 * @property string|null $label
 * @property string $type
 * @property string|null $value
 * @property array<int|string, mixed>|null $payload
 */
class SubmissionValue extends Model
{
    /**
     * What a ticked consent is stored as, whoever ticked it.
     *
     * A key and not a word: a word is in the language of whoever was answering — "Yes" from
     * the site, "Да" from a panel kept in Russian — and the same answer then read differently
     * in the export depending on who had typed it. It is put into words when it is shown.
     */
    public const CONSENTED = 'yes';

    public $timestamps = false;

    protected $table = 'inbox_submission_values';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
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
     * The field this answer was given to — null once that field has been destroyed rather
     * than put aside.
     *
     * @return BelongsTo<Field, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'field_id')->withTrashed();
    }

    /** The answer as a person reads it, in the language being read. */
    public function readable(): ?string
    {
        return self::read($this->type, $this->value);
    }

    /** The same for an answer that arrived without its row — a column of the list. */
    public static function read(?string $type, ?string $value): ?string
    {
        return $type === 'consent' && $value === self::CONSENTED
            ? (string) trans('webx-inbox::values.consented')
            : $value;
    }
}
