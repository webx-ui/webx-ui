<?php

declare(strict_types=1);

namespace WebxUi\Seo\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Seo\Targets\UrlTarget;

/**
 * The links one donor page prints (§18.4): an ordered list of acceptor and anchor, and a heading
 * that falls back to the `seo.links-heading` setting when empty.
 *
 * @property int $id
 * @property string $locale
 * @property string $path
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $heading
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoLinkBlock extends Model
{
    protected $table = 'seo_link_blocks';

    protected $fillable = ['locale', 'path', 'entity_type', 'entity_id', 'heading', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<SeoLinkItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SeoLinkItem::class, 'block_id')->orderBy('position')->orderBy('id');
    }

    public function target(): UrlTarget
    {
        return UrlTarget::fromRow($this->only(['locale', 'path', 'entity_type', 'entity_id']));
    }

    /**
     * The block of one donor: by its entity when it has one, by its path otherwise — a donor is
     * unique by either (§18.4).
     *
     * @param  Builder<SeoLinkBlock>  $query
     * @return Builder<SeoLinkBlock>
     */
    public function scopeOfTarget(Builder $query, UrlTarget $target): Builder
    {
        $query->where('locale', $target->locale);

        return $target->isBound()
            ? $query->where('entity_type', $target->entityType)->where('entity_id', $target->entityId)
            : $query->whereNull('entity_type')->where('path', $target->path);
    }
}
