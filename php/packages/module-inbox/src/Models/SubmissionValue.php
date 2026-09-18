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
}
