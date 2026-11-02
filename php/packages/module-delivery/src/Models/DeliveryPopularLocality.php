<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebxUi\Geography\Models\GeoLocality;

/**
 * A popular town of the checkout dialog (decision 40): one list for the site, by `position`.
 *
 * @property int $locality_id
 * @property int $position
 * @property-read GeoLocality|null $locality
 */
class DeliveryPopularLocality extends Model
{
    protected $table = 'delivery_popular_localities';

    protected $primaryKey = 'locality_id';

    public $incrementing = false;

    protected $fillable = ['locality_id', 'position'];

    protected function casts(): array
    {
        return [
            'locality_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<GeoLocality, $this> */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(GeoLocality::class, 'locality_id');
    }
}
