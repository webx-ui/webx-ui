<?php

declare(strict_types=1);

namespace WebxUi\Admin\Notes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line somebody left on a record for whoever picks it up next.
 *
 * Deliberately not a comment: nobody outside the panel ever sees one, there is no thread and
 * no moderation, and the whole of it is a body and a name. Visitors' comments are a different
 * thing with different readers and a different life, and they are a different module.
 *
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property int|null $admin_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Note extends Model
{
    protected $table = 'entity_notes';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'admin_id' => 'integer',
        ];
    }

    /**
     * What the note is about. Resolved through the morph map, which is what `NoteTypes`
     * fills — an alias here reads back as the model without anything knowing its class name.
     *
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    /** Whether this is the note of the administrator asking — the only one they may change. */
    public function writtenBy(mixed $admin): bool
    {
        $id = is_object($admin) && method_exists($admin, 'getAuthIdentifier')
            ? $admin->getAuthIdentifier()
            : null;

        return $id !== null && $this->admin_id !== null && (int) $id === $this->admin_id;
    }
}
