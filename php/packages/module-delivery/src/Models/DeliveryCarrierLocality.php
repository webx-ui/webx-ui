<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Geography\Models\GeoLocality;

/**
 * A carrier's locality with its own id, bound to a shared one — or waiting in the queue (§5).
 * The sync writes these in pages (`upsert`); the matcher of `webx-ui/geography` decides the
 * binding and keeps no rows itself.
 *
 * @property int $id
 * @property string $carrier
 * @property string $external_id
 * @property int|null $locality_id
 * @property string $status
 * @property string|null $method
 * @property string|null $reason
 * @property list<array{locality_id: int, distance_km: float|null}>|null $candidates
 * @property array<string, mixed> $record
 * @property list<string>|null $features
 * @property int $streets
 * @property int|null $seen_run_id
 * @property Carbon|null $removed_at
 */
class DeliveryCarrierLocality extends Model
{
    protected $table = 'delivery_carrier_localities';

    protected $fillable = [
        'carrier', 'external_id', 'locality_id', 'status', 'method', 'reason', 'candidates', 'record', 'features',
        'streets', 'seen_run_id', 'removed_at',
    ];

    protected $attributes = [
        'streets' => 0,
    ];

    protected function casts(): array
    {
        return [
            'locality_id' => 'integer',
            'candidates' => 'array',
            'record' => 'array',
            'features' => 'array',
            'streets' => 'integer',
            'removed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GeoLocality, $this> */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(GeoLocality::class, 'locality_id');
    }
}
