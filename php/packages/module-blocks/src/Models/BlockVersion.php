<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One immutable snapshot of a block type: its fields, its template, its styles, its script and
 * its sample values, all at once.
 *
 * Written on every save and never changed afterwards. A block is code, so its history is an
 * audit log as much as an undo buffer — and both are only worth something if a row, once
 * written, stays what it was.
 *
 * @property int $id
 * @property int $block_id
 * @property int $number
 * @property list<array<string, mixed>> $schema
 * @property string $template
 * @property string $styles
 * @property string|null $script
 * @property array<string, mixed> $sample
 * @property int|null $author_id
 * @property string $source
 * @property string|null $comment
 * @property Carbon|null $created_at
 */
class BlockVersion extends Model
{
    public const SOURCE_PANEL = 'panel';

    public const SOURCE_MCP = 'mcp';

    public const SOURCE_IMPORT = 'import';

    /** The fields a version is made of, in the order the editor shows them. */
    public const CONTENT = ['schema', 'template', 'styles', 'script', 'sample'];

    public const UPDATED_AT = null;

    protected $table = 'block_versions';

    protected $fillable = [
        'block_id', 'number', 'schema', 'template', 'styles', 'script', 'sample',
        'author_id', 'source', 'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'schema' => 'array',
            'sample' => 'array',
            'author_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Block, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    /**
     * The snapshot as the next version is written from: what the editor sent overrides what
     * this version had, field by field.
     *
     * @return array{schema: list<array<string, mixed>>, template: string, styles: string, script: string|null, sample: array<string, mixed>}
     */
    public function content(): array
    {
        return [
            'schema' => $this->schema ?? [],
            'template' => $this->template ?? '',
            'styles' => $this->styles ?? '',
            'script' => $this->script,
            'sample' => $this->sample ?? [],
        ];
    }
}
