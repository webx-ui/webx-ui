<?php

declare(strict_types=1);

namespace WebxUi\Routing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A former address of an entity that is in the bin.
 *
 * Not a row of `routes`: it answers nothing and claims nothing, so another entity is free to
 * take the path in the meantime. All it does is let a restore put the trail back (§9).
 *
 * @property int $id
 * @property string $locale
 * @property string $path
 * @property string $entity_type
 * @property int $entity_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TrashedAlias extends Model
{
    protected $table = 'routes_trashed';

    protected $fillable = ['locale', 'path', 'entity_type', 'entity_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['entity_id' => 'integer'];
    }

    /**
     * @param  Builder<TrashedAlias>  $query
     * @return Builder<TrashedAlias>
     */
    public function scopeForEntity(Builder $query, Model $entity): Builder
    {
        return $query
            ->where('entity_type', $entity->getMorphClass())
            ->where('entity_id', $entity->getKey());
    }
}
