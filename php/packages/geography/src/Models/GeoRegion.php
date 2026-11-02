<?php

declare(strict_types=1);

namespace WebxUi\Geography\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WebxUi\Geography\Names;
use WebxUi\Geography\Normalizer;

/**
 * A region of a country. There are hundreds, not thousands: nobody searches them, they are
 * matched by `name_key` and by the spellings in `geo_region_aliases`.
 *
 * @property int $id
 * @property string $country_code
 * @property string|null $code
 * @property array<string, string> $names
 * @property string $name_key
 */
class GeoRegion extends Model
{
    use HasManualFields;

    protected $table = 'geo_regions';

    protected $fillable = ['country_code', 'code', 'names', 'manual_fields'];

    protected function casts(): array
    {
        return [
            'names' => 'array',
            'manual_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $region): void {
            $region->name_key = Normalizer::key((string) Names::main($region->names));
        });
    }

    public function name(?string $locale = null): string
    {
        return (string) Names::pick($this->names, $locale);
    }

    /** @return BelongsTo<GeoCountry, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(GeoCountry::class, 'country_code', 'code');
    }

    /** @return HasMany<GeoRegionAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(GeoRegionAlias::class, 'region_id');
    }

    /** @return HasMany<GeoLocality, $this> */
    public function localities(): HasMany
    {
        return $this->hasMany(GeoLocality::class, 'region_id');
    }
}
