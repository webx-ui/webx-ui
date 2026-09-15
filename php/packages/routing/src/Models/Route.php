<?php

declare(strict_types=1);

namespace WebxUi\Routing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One public address.
 *
 * Every addressable entity of every kind lives in this one table, because the namespace is
 * flat: `/about`, `/blog` and `/alternator-belt-7100104` compete for the same names, and no
 * module can see another module's addresses to know that.
 *
 * A row is either the address an entity has now (`canonical`) or the address it used to have
 * (`alias`). An alias carries the entity too, so "every address of this thing" is one query and
 * deleting the thing takes its addresses with it.
 *
 * @property int $id
 * @property string $locale
 * @property string $path
 * @property string $kind
 * @property int|null $target_id
 * @property string $entity_type
 * @property int $entity_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Route extends Model
{
    public const CANONICAL = 'canonical';

    public const ALIAS = 'alias';

    protected $table = 'routes';

    protected $fillable = ['locale', 'path', 'kind', 'target_id', 'entity_type', 'entity_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'entity_id' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    /** @return BelongsTo<Route, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(self::class, 'target_id');
    }

    /** @return HasMany<Route, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(self::class, 'target_id');
    }

    public function isAlias(): bool
    {
        return $this->kind === self::ALIAS;
    }

    /**
     * @param  Builder<Route>  $query
     * @return Builder<Route>
     */
    public function scopeCanonical(Builder $query): Builder
    {
        return $query->where('kind', self::CANONICAL);
    }

    /**
     * @param  Builder<Route>  $query
     * @return Builder<Route>
     */
    public function scopeAlias(Builder $query): Builder
    {
        return $query->where('kind', self::ALIAS);
    }

    /**
     * @param  Builder<Route>  $query
     * @return Builder<Route>
     */
    public function scopeForEntity(Builder $query, Model $entity): Builder
    {
        return $query
            ->where('entity_type', $entity->getMorphClass())
            ->where('entity_id', $entity->getKey());
    }
}
