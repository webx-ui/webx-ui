<?php

declare(strict_types=1);

namespace WebxUi\Geography\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of the search index of localities: a name in a language of the site (`name`), a
 * carrier's spelling (`alias`), or the tail of either from a later word on (`word`).
 *
 * @property int $id
 * @property int $locality_id
 * @property string $country_code
 * @property string|null $locale
 * @property string $name
 * @property string $name_key
 * @property string $kind
 * @property string|null $source
 */
class GeoLocalityName extends Model
{
    public const string NAME = 'name';

    public const string ALIAS = 'alias';

    public const string WORD = 'word';

    protected $table = 'geo_locality_names';

    public $timestamps = false;

    protected $fillable = ['locality_id', 'country_code', 'locale', 'name', 'name_key', 'kind', 'source'];

    /** @return BelongsTo<GeoLocality, $this> */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(GeoLocality::class, 'locality_id');
    }
}
