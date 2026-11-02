<?php

declare(strict_types=1);

namespace WebxUi\Geography\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another spelling of a region — a carrier's, or another language's — so that the next record
 * spelled that way matches without a person.
 *
 * @property int $id
 * @property int $region_id
 * @property string $name_key
 * @property string|null $source
 */
class GeoRegionAlias extends Model
{
    protected $table = 'geo_region_aliases';

    protected $fillable = ['region_id', 'name_key', 'source'];

    /** @return BelongsTo<GeoRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(GeoRegion::class, 'region_id');
    }
}
