<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use WebxUi\Payments\Models\PaymentMethod;

/**
 * A method of the site as a type sees it (spec §6.1): immutable, without the gateway's keys —
 * only whether they are there. Built from the cached row, so reading it costs no query.
 */
final readonly class Method
{
    /**
     * @param  array<string, string>  $titles
     * @param  array<string, string>  $descriptions
     * @param  array<string, mixed>  $deliveryRule
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $key,
        public string $type,
        public array $titles,
        public array $descriptions,
        public ?string $icon,
        public bool $enabled,
        public int $position,
        public array $deliveryRule,
        public ?int $minAmount,
        public ?int $maxAmount,
        public array $settings,
        public bool $hasCredentials,
        public ?string $groupKey = null,
    ) {}

    public static function of(PaymentMethod $row): self
    {
        return self::fromArray(self::row($row));
    }

    /**
     * What the cache keeps of a row: everything but the keys themselves.
     *
     * @return array<string, mixed>
     */
    public static function row(PaymentMethod $row): array
    {
        return [
            'key' => $row->key,
            'type' => $row->type,
            'titles' => Words::clean($row->title),
            'descriptions' => Words::clean($row->description),
            'icon' => $row->icon,
            'enabled' => $row->enabled,
            'position' => $row->position,
            'group_key' => $row->group_key,
            'delivery_rule' => $row->delivery_rule,
            'min_amount' => $row->min_amount,
            'max_amount' => $row->max_amount,
            'settings' => $row->settings,
            // Only whether they are set: the cache never holds a key (§15).
            'has_credentials' => $row->getRawOriginal('credentials') !== null,
        ];
    }

    /** @param  array<string, mixed>  $row */
    public static function fromArray(array $row): self
    {
        return new self(
            key: (string) $row['key'],
            type: (string) $row['type'],
            titles: (array) $row['titles'],
            descriptions: (array) $row['descriptions'],
            icon: is_string($row['icon']) ? $row['icon'] : null,
            enabled: (bool) $row['enabled'],
            position: (int) $row['position'],
            deliveryRule: is_array($row['delivery_rule']) ? $row['delivery_rule'] : ['mode' => DeliveryRule::ANY],
            minAmount: is_numeric($row['min_amount']) ? (int) $row['min_amount'] : null,
            maxAmount: is_numeric($row['max_amount']) ? (int) $row['max_amount'] : null,
            settings: is_array($row['settings']) ? $row['settings'] : [],
            hasCredentials: (bool) $row['has_credentials'],
            // An entry cached before the column came has no such key, and means the same as null.
            groupKey: is_string($row['group_key'] ?? null) ? $row['group_key'] : null,
        );
    }

    /** The shop's title, or null — none written, the type's word stands in. */
    public function title(?string $locale = null): ?string
    {
        return Words::pick($this->titles, $locale);
    }

    public function description(?string $locale = null): ?string
    {
        return Words::pick($this->descriptions, $locale);
    }

    /** A setting the shop wrote in the languages of the site, in one of them. */
    public function words(string $setting, ?string $locale = null): ?string
    {
        $value = $this->settings[$setting] ?? null;

        return is_array($value) ? Words::pick($value, $locale) : null;
    }

    public function rule(): DeliveryRule
    {
        return DeliveryRule::fromArray($this->deliveryRule);
    }
}
