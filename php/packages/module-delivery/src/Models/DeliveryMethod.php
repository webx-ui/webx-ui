<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use WebxUi\Cache\CacheGroups;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Methods;
use WebxUi\Geography\Models\GeoLocality;

/**
 * A method the site turned on: an instance of a type with its own title, icon, price and order
 * (§5, decision 20). Two pick-ups of the shop are two rows of one type with another point kind.
 *
 * @property int $id
 * @property string $key
 * @property string $type
 * @property array<string, string> $titles
 * @property array<string, string>|null $descriptions
 * @property int|null $icon_id
 * @property string|null $point_kind
 * @property list<string>|null $countries
 * @property int|null $price
 * @property bool $honours_paid_by_store
 * @property array<string, mixed>|null $settings
 * @property bool $enabled
 * @property int $position
 */
class DeliveryMethod extends Model
{
    public const string KEY = '/^[a-z0-9-]+$/';

    protected $table = 'delivery_methods';

    protected $fillable = [
        'key', 'type', 'titles', 'descriptions', 'icon_id', 'point_kind', 'countries', 'price',
        'honours_paid_by_store', 'settings', 'enabled', 'position',
    ];

    protected $attributes = [
        'honours_paid_by_store' => true,
        'enabled' => false,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'titles' => 'array',
            'descriptions' => 'array',
            'icon_id' => 'integer',
            'countries' => 'array',
            'price' => 'integer',
            'honours_paid_by_store' => 'boolean',
            'settings' => 'array',
            'enabled' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** @return BelongsToMany<GeoLocality, $this, DeliveryMethodLocality> */
    public function localities(): BelongsToMany
    {
        return $this->belongsToMany(GeoLocality::class, 'delivery_method_localities', 'method_id', 'locality_id')
            ->using(DeliveryMethodLocality::class);
    }

    /**
     * Where a method with a list of localities goes — the shop's courier (§10.2). A sync of the
     * pivot fires no model events, so the group is moved here.
     *
     * @param  list<int>  $localities
     */
    public function syncLocalities(array $localities): void
    {
        $this->localities()->sync(array_values(array_unique($localities)));
        app(CacheGroups::class)->bump(Methods::GROUP);
    }

    /** @return array<string, mixed> the row as {@see Method::fromArray()} reads it */
    public function toValueArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'type' => $this->type,
            'titles' => $this->titles,
            'descriptions' => $this->descriptions,
            'icon_id' => $this->icon_id,
            'point_kind' => $this->point_kind,
            'countries' => $this->countries,
            'price' => $this->price,
            'honours_paid_by_store' => $this->honours_paid_by_store,
            'settings' => $this->settings ?? [],
            'enabled' => $this->enabled,
            'position' => $this->position,
            'localities' => $this->relationLoaded('localities')
                ? $this->localities->pluck('id')->map(intval(...))->values()->all()
                : [],
        ];
    }
}
