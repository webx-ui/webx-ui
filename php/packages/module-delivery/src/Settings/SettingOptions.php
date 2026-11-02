<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Settings;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\ProvidesSettingOptions;
use WebxUi\Delivery\Sync\CarrierUnavailable;

/**
 * The options of a carrier's settings fields from its own account (§13.1, decision 6 of the
 * satellite's spec): asked for the panel's select and the agent's `delivery_carriers_options`,
 * checked again on every save, and remembered by their labels in `settings._labels`, so that the
 * screen shows «Sender A» without asking the carrier — and has something to show when it is down.
 *
 * The options are kept ten minutes in the plain cache, not in `CacheGroups`: nothing the site
 * edits changes them. The key holds a fingerprint of the stored keys, so a new key asks anew;
 * «Refresh lists» and a save go past the cache.
 */
final class SettingOptions
{
    /** Where the labels of the chosen values live inside `delivery_carriers.settings`. */
    public const string LABELS = '_labels';

    public const int TTL = 600;

    /** No more options than this reach the panel or an agent. */
    public const int LIMIT = 50;

    public function __construct(private readonly Repository $cache) {}

    /**
     * The carrier's fields whose options come from its account, by name, in their order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(Carrier $carrier): array
    {
        if (! $carrier instanceof ProvidesSettingOptions) {
            return [];
        }

        $fields = [];

        foreach ($carrier->settings() as $field) {
            if (is_string($field['name'] ?? null) && ($field['options'] ?? null) === 'carrier') {
                $fields[$field['name']] = $field;
            }
        }

        return $fields;
    }

    /**
     * The fields a field depends on.
     *
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    public static function depends(array $field): array
    {
        return array_values(array_filter((array) ($field['depends'] ?? []), 'is_string'));
    }

    /**
     * The options of one field for the values chosen so far.
     *
     * @param  array<string, mixed>  $values  the form's values; only what the field depends on is sent
     * @param  array<string, mixed>  $credentials  the stored keys
     * @return list<SettingOption>
     *
     * @throws SettingOptionsRefused no such field, no key, a parent not chosen
     * @throws CarrierUnavailable the carrier did not answer or refused the key
     */
    public function options(Carrier $carrier, string $field, array $values, array $credentials, ?string $query = null, bool $fresh = false): array
    {
        if (! $carrier instanceof ProvidesSettingOptions) {
            throw new SettingOptionsRefused((string) __('webx-delivery::panel.options.no-options', ['carrier' => $carrier->key()]), 404);
        }

        $definition = self::fields($carrier)[$field]
            ?? throw new SettingOptionsRefused((string) __('webx-delivery::panel.options.no-field', ['field' => $field]), 404);

        if ($carrier->credentials() !== [] && ! self::keyed($credentials)) {
            throw new SettingOptionsRefused((string) __('webx-delivery::panel.options.no-key'));
        }

        $parents = [];

        foreach (self::depends($definition) as $parent) {
            $value = $values[$parent] ?? null;

            if (self::blank($value)) {
                throw new SettingOptionsRefused((string) __('webx-delivery::panel.options.choose-first', ['field' => self::label($carrier, $parent)]));
            }

            $parents[$parent] = is_scalar($value) ? (string) $value : $value;
        }

        $query = ($definition['search'] ?? false) === true && is_string($query) && trim($query) !== '' ? trim($query) : null;
        $key = $this->key($carrier->key(), $field, $parents, $query, $credentials);

        if (! $fresh) {
            $cached = $this->cache->get($key);

            if (is_array($cached)) {
                return self::revive($cached);
            }
        }

        try {
            $options = array_slice(array_values(array_filter(
                $carrier->settingOptions($field, $parents, $credentials, $query),
                static fn (mixed $one): bool => $one instanceof SettingOption,
            )), 0, self::LIMIT);
        } catch (CarrierUnavailable $failure) {
            // The exception's own text may carry a request: the log gets it, never the panel.
            Log::warning("Delivery: the options of «{$carrier->key()}.{$field}» did not come: {$failure->getMessage()}");

            throw $failure;
        }

        $this->cache->put($key, array_map(static fn (SettingOption $one): array => $one->toArray(), $options), self::TTL);

        return $options;
    }

    /**
     * Check the fields with options a save changes, past the cache (§5.4 of the satellite's spec):
     * only a value from the carrier's list is taken, and its label goes to `_labels`. A field whose
     * parent changed is checked too; an emptied field loses its label.
     *
     * @param  array<string, mixed>  $before  what is stored
     * @param  array<string, mixed>  $after  what would be stored
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed> `$after` with its labels
     *
     * @throws SettingsRejected field → words
     */
    public function check(Carrier $carrier, array $before, array $after, array $credentials): array
    {
        $fields = self::fields($carrier);

        if ($fields === []) {
            return $after;
        }

        $labels = is_array($after[self::LABELS] ?? null) ? $after[self::LABELS] : [];
        $errors = [];

        foreach ($fields as $name => $field) {
            $value = $after[$name] ?? null;

            if (self::blank($value)) {
                unset($labels[$name]);

                continue;
            }

            $moved = self::same($value, $before[$name] ?? null) === false;

            foreach (self::depends($field) as $parent) {
                $moved = $moved || ! self::same($after[$parent] ?? null, $before[$parent] ?? null) || isset($errors[$parent]);
            }

            if (! $moved && isset($labels[$name])) {
                continue;
            }

            try {
                $search = ($field['search'] ?? false) === true;
                $options = $this->options($carrier, $name, $after, $credentials, $search ? self::text($value) : null, fresh: true);
            } catch (SettingOptionsRefused $refused) {
                $errors[$name] = $refused->getMessage();

                continue;
            } catch (CarrierUnavailable $failure) {
                $errors[$name] = self::unavailable($failure);

                continue;
            }

            $match = self::find($options, $value);

            if ($match === null) {
                $errors[$name] = (string) __('webx-delivery::panel.options.choose-from-list');

                continue;
            }

            $labels[$name] = $match->label;
        }

        if ($errors !== []) {
            throw new SettingsRejected($errors);
        }

        $labels = array_intersect_key($labels, $fields);

        if ($labels === []) {
            unset($after[self::LABELS]);

            return $after;
        }

        return [...$after, self::LABELS => $labels];
    }

    /**
     * Chosen values the carrier's account no longer has (a contact deleted there): field → its
     * label. The value stays as it is — a person decides. With `$ask = false` only what is cached
     * is looked at: a list of carriers asks no carrier.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    public function missing(Carrier $carrier, array $settings, array $credentials, bool $ask = true): array
    {
        $labels = is_array($settings[self::LABELS] ?? null) ? $settings[self::LABELS] : [];
        $missing = [];

        foreach (self::fields($carrier) as $name => $field) {
            $value = $settings[$name] ?? null;

            if (self::blank($value)) {
                continue;
            }

            $search = ($field['search'] ?? false) === true;

            try {
                if ($ask) {
                    $options = $this->options($carrier, $name, $settings, $credentials, $search ? self::text($value) : null);
                } else {
                    $cached = $this->cached($carrier, $name, $settings, $credentials, $search ? self::text($value) : null);

                    if ($cached === null) {
                        continue;
                    }

                    $options = $cached;
                }
            } catch (SettingOptionsRefused|CarrierUnavailable) {
                // Not known now: the dialog says so when it is opened.
                continue;
            }

            if (self::find($options, $value) === null) {
                $missing[$name] = is_string($labels[$name] ?? null) ? $labels[$name] : self::text($value);
            }
        }

        return $missing;
    }

    /** The words of a carrier that did not give its list: «Could not get the list…: <why>». */
    public static function unavailable(CarrierUnavailable $failure): string
    {
        return (string) __('webx-delivery::panel.options.unavailable', ['reason' => $failure->failure->words()]);
    }

    /** @param  array<string, mixed>  $credentials */
    private static function keyed(array $credentials): bool
    {
        foreach ($credentials as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $credentials
     * @return list<SettingOption>|null
     */
    private function cached(Carrier $carrier, string $field, array $values, array $credentials, ?string $query): ?array
    {
        $definition = self::fields($carrier)[$field] ?? null;

        if ($definition === null) {
            return null;
        }

        $parents = [];

        foreach (self::depends($definition) as $parent) {
            $value = $values[$parent] ?? null;
            $parents[$parent] = is_scalar($value) ? (string) $value : $value;
        }

        $query = ($definition['search'] ?? false) === true ? $query : null;
        $cached = $this->cache->get($this->key($carrier->key(), $field, $parents, $query, $credentials));

        return is_array($cached) ? self::revive($cached) : null;
    }

    /**
     * @param  array<string, mixed>  $parents
     * @param  array<string, mixed>  $credentials
     */
    private function key(string $carrier, string $field, array $parents, ?string $query, array $credentials): string
    {
        ksort($credentials);

        return implode(':', [
            'delivery.options',
            $carrier,
            $field,
            hash('xxh128', (string) json_encode([$parents, $query])),
            // A fingerprint of the key, never the key: another key — other options.
            hash('xxh128', (string) json_encode($credentials)),
        ]);
    }

    /**
     * @param  array<mixed>  $cached
     * @return list<SettingOption>
     */
    private static function revive(array $cached): array
    {
        $options = [];

        foreach ($cached as $one) {
            if (is_array($one) && isset($one['value'], $one['label'])) {
                $options[] = new SettingOption((string) $one['value'], (string) $one['label'], isset($one['hint']) ? (string) $one['hint'] : null);
            }
        }

        return $options;
    }

    /** @param  list<SettingOption>  $options */
    private static function find(array $options, mixed $value): ?SettingOption
    {
        foreach ($options as $option) {
            if ($option->value === self::text($value)) {
                return $option;
            }
        }

        return null;
    }

    private static function label(Carrier $carrier, string $name): string
    {
        foreach ($carrier->settings() as $field) {
            if (($field['name'] ?? null) !== $name) {
                continue;
            }

            $label = $field['label'] ?? null;

            if (is_string($label)) {
                return $label;
            }

            if (is_array($label)) {
                $locale = app()->getLocale();
                $text = $label[$locale] ?? $label['en'] ?? reset($label);

                return is_string($text) ? $text : $name;
            }
        }

        return $name;
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
    }

    private static function same(mixed $one, mixed $other): bool
    {
        return self::text($one) === self::text($other);
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }
}
