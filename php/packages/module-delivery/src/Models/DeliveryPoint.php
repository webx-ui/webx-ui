<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Delivery\Carriers;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Normalizer;

/**
 * A point of a carrier or of the shop (`carrier = store`): one table for both, so that the search
 * and the budget of queries are one (decision 21).
 *
 * @property int $id
 * @property string $carrier
 * @property string|null $external_id
 * @property string $kind
 * @property int|null $locality_id
 * @property int|null $carrier_locality_id
 * @property string|null $number
 * @property array<string, string>|null $titles
 * @property array<string, string> $address
 * @property string|null $postcode
 * @property float|null $lat
 * @property float|null $lng
 * @property array<string, mixed>|null $schedule
 * @property array<string, mixed>|null $limits
 * @property string|null $phone
 * @property string $search
 * @property array<string, mixed>|null $meta
 * @property bool $active
 * @property string|null $restriction
 * @property int|null $seen_run_id
 * @property Carbon|null $removed_at
 * @property-read GeoLocality|null $locality
 */
class DeliveryPoint extends Model
{
    protected $table = 'delivery_points';

    protected $fillable = [
        'carrier', 'external_id', 'kind', 'locality_id', 'carrier_locality_id', 'number', 'titles',
        'address', 'postcode', 'lat', 'lng', 'schedule', 'limits', 'phone', 'meta', 'active',
        'restriction', 'seen_run_id', 'removed_at',
    ];

    protected $attributes = [
        'carrier' => Carriers::STORE,
        'active' => true,
        'search' => '',
    ];

    protected function casts(): array
    {
        return [
            'locality_id' => 'integer',
            'carrier_locality_id' => 'integer',
            'titles' => 'array',
            'address' => 'array',
            'lat' => 'float',
            'lng' => 'float',
            'schedule' => 'array',
            'limits' => 'array',
            'meta' => 'array',
            'active' => 'boolean',
            'removed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // What the search inside a locality reads (§12.2). A sync writing by `upsert` builds the
        // same string with {@see self::searchOf()}.
        static::saving(static function (self $point): void {
            $point->search = self::searchOf($point->number, $point->titles, $point->address);
        });
    }

    /**
     * Number, titles and address in every language, normalised and joined.
     *
     * @param  array<string, string>|null  $titles
     * @param  array<string, string>|null  $address
     */
    public static function searchOf(?string $number, ?array $titles, ?array $address): string
    {
        $parts = [];

        foreach ([[$number], array_values($titles ?? []), array_values($address ?? [])] as $group) {
            foreach ($group as $part) {
                $key = is_string($part) ? Normalizer::key($part) : '';

                if ($key !== '' && ! in_array($key, $parts, true)) {
                    $parts[] = $key;
                }
            }
        }

        return mb_substr(implode(' ', $parts), 0, 500, 'UTF-8');
    }

    /** @return BelongsTo<GeoLocality, $this> */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(GeoLocality::class, 'locality_id');
    }
}
