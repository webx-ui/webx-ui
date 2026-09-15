<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * A block type: what it is called, where it may go, and two pointers into its own history.
 *
 * The content — fields, template, styles, script, sample — lives in the versions. This row
 * holds what identifies the type and constrains its use, plus which version is being edited
 * (`draft`) and which one the site prints (`published`). Saving writes a version; publishing
 * moves a pointer. Nothing here is ever edited in place.
 *
 * `title` and `description` are plain strings on purpose: the labels of the block's own fields
 * live in `schema`, where a translatable string is a `trans::` marker, and a title translated
 * by some other mechanism would come apart from them in the form, the export and MCP. A team
 * that needs a multilingual title writes the same marker here.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string|null $icon
 * @property string $group
 * @property int $sort
 * @property list<string>|null $allow
 * @property list<string>|null $allowed_in
 * @property int|null $max_per_entity
 * @property bool $is_enabled
 * @property int|null $draft_version_id
 * @property int|null $published_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BlockVersion|null $draftVersion
 * @property-read BlockVersion|null $publishedVersion
 */
class Block extends Model
{
    protected $table = 'blocks';

    protected $fillable = [
        'slug', 'title', 'description', 'icon', 'group', 'sort',
        'allow', 'allowed_in', 'max_per_entity', 'is_enabled',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'group' => 'content',
        'sort' => 0,
        'is_enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'allow' => 'array',
            'allowed_in' => 'array',
            'max_per_entity' => 'integer',
            'is_enabled' => 'boolean',
            'draft_version_id' => 'integer',
            'published_version_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // A change to the row itself — a new sort, a disabled flag — is a change to the
        // registry's list; the pointers move through `saveVersion()` and `publish()`, which
        // save the row too.
        $forget = static function (): void {
            Container::getInstance()->make(BlockTypes::class)->forget();
        };

        static::saved($forget);
        static::deleted($forget);

        // The database cascades too, where it is asked to; this holds on a connection that
        // does not enforce foreign keys, and a version without its block is a row nobody can
        // reach.
        static::deleting(static function (Block $block): void {
            $block->versions()->delete();
        });
    }

    /** @return HasMany<BlockVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(BlockVersion::class, 'block_id')->orderByDesc('number');
    }

    /** @return BelongsTo<BlockVersion, $this> */
    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(BlockVersion::class, 'draft_version_id');
    }

    /** @return BelongsTo<BlockVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(BlockVersion::class, 'published_version_id');
    }

    /**
     * @param  Builder<Block>  $query
     * @return Builder<Block>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_version_id');
    }

    /** The version the editor is working on: the draft, or the published one when there is no draft. */
    public function currentVersion(): ?BlockVersion
    {
        return $this->draftVersion ?? $this->publishedVersion;
    }

    /**
     * Write the next version and make it the draft.
     *
     * Only what is sent has to be sent: a field left out keeps its value from the version being
     * edited, so `update_block` can send a template alone. The first version of a new block
     * starts from nothing.
     *
     * @param  array<string, mixed>  $content  Any of `schema`, `template`, `styles`, `script`, `sample`.
     */
    public function saveVersion(
        array $content,
        string $source = BlockVersion::SOURCE_PANEL,
        ?int $authorId = null,
        ?string $comment = null,
    ): BlockVersion {
        if (! $this->exists) {
            $this->save();
        }

        $base = $this->currentVersion()?->content() ?? [
            'schema' => [], 'template' => '', 'styles' => '', 'script' => null, 'sample' => [],
        ];

        $content = array_intersect_key($content, array_flip(BlockVersion::CONTENT)) + $base;

        $number = (int) $this->versions()->max('number') + 1;

        $version = $this->versions()->create($content + [
            'number' => $number,
            'author_id' => $authorId,
            'source' => $source,
            'comment' => $comment,
            'created_at' => Carbon::now(),
        ]);

        $this->draft_version_id = $version->id;
        $this->save();
        $this->setRelation('draftVersion', $version);

        return $version;
    }

    /**
     * Make a version the one the site prints.
     *
     * Refused when the template does not compile or fails on the sample values: one typo in a
     * template would otherwise take down every page the block stands on, and we would hear
     * about it from a visitor.
     *
     * @throws BlocksException
     */
    public function publish(?BlockVersion $version = null): BlockVersion
    {
        $version ??= $this->draftVersion;

        if (! $version instanceof BlockVersion) {
            throw new BlocksException("Block '{$this->slug}' has no draft to publish.");
        }

        if ($version->block_id !== $this->id) {
            throw new BlocksException("Version {$version->number} does not belong to block '{$this->slug}'.");
        }

        Container::getInstance()->make(Renderer::class)->check(BlockType::fromModels($this, $version));

        $this->published_version_id = $version->id;

        if ($this->draft_version_id === $version->id) {
            $this->draft_version_id = null;
            $this->unsetRelation('draftVersion');
        }

        $this->save();
        $this->setRelation('publishedVersion', $version);

        return $version;
    }
}
