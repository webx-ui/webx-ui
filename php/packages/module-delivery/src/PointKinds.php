<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use InvalidArgumentException;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Geography\Names;

/**
 * Kinds of points (§3): the built-in ones with words of the dictionary, and the kinds the shop
 * names itself for its own pick-ups («New kind of points», §10.1) — kept by language in the
 * settings of the `store` carrier row, as they are the shop's and nobody else's.
 */
final class PointKinds
{
    /** The kinds of the dictionary: carriers' branches and lockers, the shop's stores and pick-up points. */
    public const array BUILT_IN = ['branch', 'locker', 'store', 'pickup'];

    /** What a pick-up of the shop offers before the shop names a kind of its own. */
    public const array STORE_KINDS = ['store', 'pickup'];

    public const string KEY = '/^[a-z0-9-]+$/';

    /**
     * Every kind with its name in this language: the built-in ones, then the shop's own.
     *
     * @return array<string, string>
     */
    public function all(?string $locale = null): array
    {
        $kinds = [];

        foreach (self::BUILT_IN as $kind) {
            $kinds[$kind] = (string) trans("webx-delivery::delivery.point_kinds.{$kind}", [], $locale);
        }

        foreach ($this->own() as $kind => $names) {
            $kinds[$kind] = (string) (Names::pick($names, $locale) ?? $kind);
        }

        return $kinds;
    }

    public function has(string $kind): bool
    {
        return in_array($kind, self::BUILT_IN, true) || array_key_exists($kind, $this->own());
    }

    public function label(string $kind, ?string $locale = null): string
    {
        return $this->all($locale)[$kind] ?? $kind;
    }

    /**
     * Names a kind of the shop's own points, or renames it.
     *
     * @param  array<string, string>  $names  by language
     */
    public function define(string $kind, array $names): void
    {
        if (preg_match(self::KEY, $kind) !== 1 || strlen($kind) > 32) {
            throw new InvalidArgumentException("[{$kind}] is not a kind of points: lowercase letters, digits and dashes, at most 32 characters.");
        }

        if (in_array($kind, self::BUILT_IN, true)) {
            throw new DeliveryException("Kind of points {$kind} is built in and keeps the name of the dictionary.");
        }

        $names = Names::clean($names);

        if ($names === []) {
            throw new DeliveryException("Kind of points {$kind} needs a name.");
        }

        $store = $this->store();
        $settings = $store->settings ?? [];
        $own = is_array($settings['point_kinds'] ?? null) ? $settings['point_kinds'] : [];
        $own[$kind] = $names;
        $settings['point_kinds'] = $own;
        $store->settings = $settings;
        $store->save();
    }

    /** @return array<string, array<string, string>> */
    public function own(): array
    {
        $settings = DeliveryCarrier::query()->find(Carriers::STORE)->settings ?? [];
        $own = is_array($settings['point_kinds'] ?? null) ? $settings['point_kinds'] : [];
        $kinds = [];

        foreach ($own as $kind => $names) {
            if (is_string($kind) && is_array($names)) {
                $kinds[$kind] = Names::clean($names);
            }
        }

        return $kinds;
    }

    private function store(): DeliveryCarrier
    {
        return DeliveryCarrier::query()->firstOrCreate(['key' => Carriers::STORE], ['enabled' => true]);
    }
}
