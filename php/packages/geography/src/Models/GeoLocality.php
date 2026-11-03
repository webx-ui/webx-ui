<?php

declare(strict_types=1);

namespace WebxUi\Geography\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WebxUi\Geography\LocalityKind;
use WebxUi\Geography\NameIndex;
use WebxUi\Geography\Names;
use WebxUi\Geography\Normalizer;

/**
 * A locality of the shared geography: what a buyer picks once and every carrier binds its own
 * id to.
 *
 * @property int $id
 * @property string $country_code
 * @property int|null $region_id
 * @property array<string, string>|null $district
 * @property string $kind
 * @property array<string, string> $names
 * @property string $name_key
 * @property float|null $lat
 * @property float|null $lng
 * @property int $weight
 * @property bool $active
 * @property string $created_by
 * @property-read GeoRegion|null $region
 */
class GeoLocality extends Model
{
    use HasManualFields;

    /** Made by an administrator in the panel or through MCP. */
    public const string MANUAL = 'manual';

    /** Brought by `webx:geo:import`. */
    public const string IMPORT = 'import';

    protected $table = 'geo_localities';

    protected $fillable = [
        'country_code', 'region_id', 'district', 'kind', 'names', 'lat', 'lng', 'weight', 'active',
        'created_by', 'manual_fields',
    ];

    protected $attributes = [
        'weight' => 0,
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'district' => 'array',
            'names' => 'array',
            'lat' => 'float',
            'lng' => 'float',
            'weight' => 'integer',
            'active' => 'boolean',
            'manual_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $locality): void {
            $locality->name_key = Normalizer::key((string) Names::main($locality->names));
        });

        // The search index follows the names; a save that touched neither names nor country
        // leaves it be, so that a weight recount does not rewrite thousands of rows.
        static::saved(static function (self $locality): void {
            if ($locality->wasRecentlyCreated || $locality->wasChanged(['names', 'country_code'])) {
                NameIndex::sync($locality);
            }
        });
    }

    /** A record of a carrier keeps a field the administrator changed (decision 42). */
    public function isManual(string $field): bool
    {
        return $this->created_by === self::MANUAL || in_array($field, $this->manual_fields ?? [], true);
    }

    public function kindEnum(): LocalityKind
    {
        return LocalityKind::tryFrom($this->kind) ?? LocalityKind::Other;
    }

    public function name(?string $locale = null): string
    {
        return (string) Names::pick($this->names, $locale);
    }

    /** "Town D, District C, Region B" — the line two places of one name are told apart by. */
    public function label(?string $locale = null): string
    {
        $name = $this->name($locale);
        $seen = [Normalizer::key($name)];
        $parts = [$name];

        // «Kyiv, Kyiv» says nothing twice: a city that is its own region (or district) is named once.
        foreach ([Names::pick($this->district, $locale), $this->region?->name($locale)] as $part) {
            if ($part === null || $part === '' || in_array(Normalizer::key($part), $seen, true)) {
                continue;
            }

            $seen[] = Normalizer::key($part);
            $parts[] = $part;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** @return BelongsTo<GeoCountry, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(GeoCountry::class, 'country_code', 'code');
    }

    /** @return BelongsTo<GeoRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(GeoRegion::class, 'region_id');
    }

    /** @return HasMany<GeoLocalityName, $this> */
    public function searchNames(): HasMany
    {
        return $this->hasMany(GeoLocalityName::class, 'locality_id');
    }
}
