<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Geography\Matching\LeadingCarriers;

/**
 * The leading carrier of each country (§4.4), remembered by the site: the first carrier to sync
 * a country leads it from then on, in every process, whatever order the nights run in. Kept in
 * the `meta` of the shop's own carrier row; `webx-geography.countries.<code>.source` set in the
 * config wins over it. The panel's geography chooses another leader with {@see choose()}.
 */
final class StoredLeadingCarriers implements LeadingCarriers
{
    private const string META = 'leading_carriers';

    /** @var array<string, string>|null */
    private ?array $stored = null;

    public function __construct(private readonly Config $config) {}

    public function leader(string $country): ?string
    {
        $country = strtoupper($country);
        $configured = $this->config->get("webx-geography.countries.{$country}.source");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->stored()[$country] ?? null;
    }

    public function claim(string $country, string $carrier): string
    {
        $leader = $this->leader($country);

        if ($leader !== null) {
            return $leader;
        }

        $row = DeliveryCarrier::query()->firstOrCreate(['key' => Carriers::STORE]);
        $meta = $row->meta ?? [];
        $leaders = is_array($meta[self::META] ?? null) ? $meta[self::META] : [];
        $leaders[strtoupper($country)] ??= $carrier;
        $meta[self::META] = $leaders;
        $row->forceFill(['meta' => $meta])->save();

        $this->stored = array_filter($leaders, is_string(...));

        return $this->stored[strtoupper($country)];
    }

    /**
     * The panel's choice (§14.2): this carrier leads the country from now on; `null` forgets the
     * leader, and the next carrier to sync the country claims it again.
     */
    public function choose(string $country, ?string $carrier): void
    {
        $country = strtoupper($country);
        $row = DeliveryCarrier::query()->firstOrCreate(['key' => Carriers::STORE]);
        $meta = $row->meta ?? [];
        $leaders = is_array($meta[self::META] ?? null) ? $meta[self::META] : [];

        if ($carrier === null) {
            unset($leaders[$country]);
        } else {
            $leaders[$country] = $carrier;
        }

        $meta[self::META] = $leaders;
        $row->forceFill(['meta' => $meta])->save();

        $this->stored = array_filter($leaders, is_string(...));
    }

    /** Whether the config decides the country, so the panel cannot (§4.4). */
    public function configured(string $country): bool
    {
        $configured = $this->config->get('webx-geography.countries.'.strtoupper($country).'.source');

        return is_string($configured) && $configured !== '';
    }

    /** @return array<string, string> */
    private function stored(): array
    {
        if ($this->stored === null) {
            $meta = DeliveryCarrier::query()->whereKey(Carriers::STORE)->value('meta');
            $meta = is_string($meta) ? json_decode($meta, true) : $meta;
            $leaders = is_array($meta) && is_array($meta[self::META] ?? null) ? $meta[self::META] : [];
            $this->stored = array_filter($leaders, is_string(...));
        }

        return $this->stored;
    }
}
