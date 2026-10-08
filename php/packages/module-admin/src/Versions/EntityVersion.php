<?php

declare(strict_types=1);

namespace WebxUi\Admin\Versions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One snapshot of an entity: what its columns held the moment it was published, or what its
 * draft held the moment it was saved.
 *
 * Three kinds. A `published` version is the site's past — numbered, listed in the history, kept
 * up to a limit. An `autosave` is insurance, not history: the last few copies of the draft, so
 * that a block deleted by mistake and saved over can be had back. An `overwritten` one is the
 * draft somebody else had written, kept at the moment a save replaced it — an agent's edit
 * under an editor's, an editor's under an agent's. It has a ring of its own, because the
 * autosaves of the one still typing would push it out of theirs within a minute, and a
 * publication does not drop it: it is what the publication may have lost. None is ever
 * changed after it is written; pinning is the one exception, and it only says "do not prune
 * this".
 *
 * @property int $id
 * @property string $versionable_type
 * @property int $versionable_id
 * @property int|null $number
 * @property string $kind
 * @property array<string, mixed> $payload
 * @property bool $is_pinned
 * @property int|null $author_id
 * @property string $source
 * @property string|null $comment
 * @property Carbon|null $created_at
 */
class EntityVersion extends Model
{
    public const KIND_PUBLISHED = 'published';

    public const KIND_AUTOSAVE = 'autosave';

    public const KIND_OVERWRITTEN = 'overwritten';

    public const SOURCE_PANEL = 'panel';

    public const SOURCE_MCP = 'mcp';

    public const SOURCE_IMPORT = 'import';

    public const UPDATED_AT = null;

    protected $table = 'cms_versions';

    protected $fillable = [
        'versionable_type', 'versionable_id', 'number', 'kind', 'payload',
        'is_pinned', 'author_id', 'source', 'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'versionable_id' => 'integer',
            'number' => 'integer',
            'payload' => 'array',
            'is_pinned' => 'boolean',
            'author_id' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function versionable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'versionable_type', 'versionable_id');
    }

    /**
     * @param  Builder<EntityVersion>  $query
     * @return Builder<EntityVersion>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_PUBLISHED);
    }

    /**
     * @param  Builder<EntityVersion>  $query
     * @return Builder<EntityVersion>
     */
    public function scopeAutosaves(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_AUTOSAVE);
    }

    /**
     * The copies of the draft rather than the publications: the autosave ring and the drafts
     * that were written over.
     *
     * @param  Builder<EntityVersion>  $query
     * @return Builder<EntityVersion>
     */
    public function scopeDrafts(Builder $query): Builder
    {
        return $query->whereIn('kind', [self::KIND_AUTOSAVE, self::KIND_OVERWRITTEN]);
    }

    public function isPublished(): bool
    {
        return $this->kind === self::KIND_PUBLISHED;
    }

    /** Keep this version whatever the limit says. */
    public function pin(bool $pinned = true): static
    {
        $this->forceFill(['is_pinned' => $pinned])->save();

        return $this;
    }
}
