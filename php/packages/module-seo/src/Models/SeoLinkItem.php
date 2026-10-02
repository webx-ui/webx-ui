<?php

declare(strict_types=1);

namespace WebxUi\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebxUi\Seo\Targets\UrlTarget;

/**
 * One link of a donor's block: where it leads and the words it is written with.
 *
 * @property int $id
 * @property int $block_id
 * @property string $locale
 * @property string $path
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string $anchor
 * @property int $position
 */
class SeoLinkItem extends Model
{
    protected $table = 'seo_link_items';

    public $timestamps = false;

    protected $fillable = ['block_id', 'locale', 'path', 'entity_type', 'entity_id', 'anchor', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'block_id' => 'integer',
            'entity_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<SeoLinkBlock, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(SeoLinkBlock::class, 'block_id');
    }

    public function target(): UrlTarget
    {
        return UrlTarget::fromRow($this->only(['locale', 'path', 'entity_type', 'entity_id']));
    }
}
