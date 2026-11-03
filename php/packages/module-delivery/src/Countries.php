<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Cache\CacheGroups;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Names;

/**
 * The countries the shop delivers to: the active ones of the shared geography, in the panel's
 * order. Every request of the step reads them, so they live in the cache group `delivery`, which
 * `GeoCountry` bumps (§17). One country — the choice is not shown (decision 17).
 */
final class Countries
{
    public function __construct(private readonly CacheGroups $groups) {}

    /**
     * @return list<array{code: string, name: string}>
     */
    public function active(?string $locale = null): array
    {
        /** @var array<string, array<string, string>|null> $rows */
        $rows = $this->groups->remember('delivery.countries', [Methods::GROUP], static fn (): array => GeoCountry::query()
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('code')
            ->pluck('names', 'code')
            ->map(static fn (mixed $names): ?array => is_array($names) ? $names : (is_string($names) ? json_decode($names, true) : null))
            ->all(), localized: false);

        $countries = [];

        foreach ($rows as $code => $names) {
            $code = (string) $code;
            $countries[] = [
                'code' => $code,
                'name' => Names::pick(is_array($names) ? $names : null, $locale) ?? GeoCountry::intlName($code, $locale ?? app()->getLocale()),
            ];
        }

        return $countries;
    }

    public function has(string $code): bool
    {
        foreach ($this->active() as $country) {
            if ($country['code'] === strtoupper($code)) {
                return true;
            }
        }

        return false;
    }

    /** The one country there is to pick, or null: none, or a choice. */
    public function only(): ?string
    {
        $active = $this->active();

        return count($active) === 1 ? $active[0]['code'] : null;
    }
}
