<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Geography\Names;

/**
 * A method the site turned on, as its type is given it: a copy of the row of `delivery_methods`
 * with the localities of a courier beside it — never the model (§6.1). It is also what the cache
 * of the group `delivery` keeps, as an array ({@see self::toArray()}), since a cache store may
 * unserialize no classes.
 */
final readonly class Method
{
    /**
     * @param  array<string, string>  $titles
     * @param  array<string, string>|null  $descriptions
     * @param  list<string>|null  $countries  ISO 3166-1; null — every country of the type
     * @param  array<string, mixed>  $settings
     * @param  list<int>  $localities  where a method with a list of localities goes (§10.2)
     */
    public function __construct(
        public int $id,
        public string $key,
        public string $type,
        public array $titles,
        public ?array $descriptions = null,
        public ?int $iconId = null,
        public ?string $pointKind = null,
        public ?array $countries = null,
        public ?int $price = null,
        public bool $honoursPaidByStore = true,
        public array $settings = [],
        public bool $enabled = true,
        public int $position = 0,
        public array $localities = [],
    ) {}

    public static function of(DeliveryMethod $method): self
    {
        return self::fromArray($method->toValueArray());
    }

    /** @param  array<string, mixed>  $row  what {@see self::toArray()} gave */
    public static function fromArray(array $row): self
    {
        /** @var array<string, string> $titles */
        $titles = $row['titles'] ?? [];
        /** @var array<string, string>|null $descriptions */
        $descriptions = $row['descriptions'] ?? null;
        /** @var list<string>|null $countries */
        $countries = $row['countries'] ?? null;
        /** @var array<string, mixed> $settings */
        $settings = $row['settings'] ?? [];
        /** @var list<int> $localities */
        $localities = $row['localities'] ?? [];

        return new self(
            id: (int) $row['id'],
            key: (string) $row['key'],
            type: (string) $row['type'],
            titles: $titles,
            descriptions: $descriptions,
            iconId: isset($row['icon_id']) ? (int) $row['icon_id'] : null,
            pointKind: isset($row['point_kind']) ? (string) $row['point_kind'] : null,
            countries: $countries,
            price: isset($row['price']) ? (int) $row['price'] : null,
            honoursPaidByStore: (bool) ($row['honours_paid_by_store'] ?? true),
            settings: $settings,
            enabled: (bool) ($row['enabled'] ?? true),
            position: (int) ($row['position'] ?? 0),
            localities: array_values(array_map(intval(...), $localities)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'type' => $this->type,
            'titles' => $this->titles,
            'descriptions' => $this->descriptions,
            'icon_id' => $this->iconId,
            'point_kind' => $this->pointKind,
            'countries' => $this->countries,
            'price' => $this->price,
            'honours_paid_by_store' => $this->honoursPaidByStore,
            'settings' => $this->settings,
            'enabled' => $this->enabled,
            'position' => $this->position,
            'localities' => $this->localities,
        ];
    }

    public function title(?string $locale = null): string
    {
        return (string) (Names::pick($this->titles, $locale) ?? $this->key);
    }

    public function description(?string $locale = null): ?string
    {
        return Names::pick($this->descriptions, $locale);
    }

    /** The carrier part of the type's key: 'store' of 'store.pickup'. */
    public function carrier(): string
    {
        return explode('.', $this->type, 2)[0];
    }

    public function goesTo(int $locality): bool
    {
        return in_array($locality, $this->localities, true);
    }
}
