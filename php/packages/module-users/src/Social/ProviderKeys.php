<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use Illuminate\Support\Env;
use Illuminate\Support\Str;
use WebxUi\Admin\Support\Field;
use WebxUi\Users\Models\ProviderSettings;

/**
 * The keys of the providers and whether each is shown (spec §9.3).
 *
 * A value is the panel's (`users_providers`), or where the field there is empty the site's
 * `.env` (`WEBX_USERS_<KEY>_<FIELD>`, or `webx-users.social.keys.<key>.<field>` in a cached
 * config). A provider is **shown** only when it is switched on — in the panel, or by
 * `WEBX_USERS_<KEY>_ENABLED` while the panel has no row of it — and every required field has a
 * value. One that is not has no button and no route.
 *
 * Scoped: the rows are read once a request, and not carried into the next one.
 */
final class ProviderKeys
{
    /** @var array<string, ProviderSettings>|null */
    private ?array $rows = null;

    public function __construct(private readonly SocialProviders $providers) {}

    public function config(Provider $provider): ProviderConfig
    {
        $row = $this->row($provider->key());
        $fallback = [];

        foreach ($provider->fields() as $field) {
            $fallback[$field->name] = $this->env($provider->key(), $field->name);
        }

        return new ProviderConfig($row->options ?? [], $row->credentials ?? [], $fallback);
    }

    public function enabled(Provider $provider): bool
    {
        $row = $this->row($provider->key());

        if ($row !== null) {
            return $row->enabled;
        }

        return filter_var($this->env($provider->key(), 'enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The required fields with no value anywhere — what the settings say is missing before the
     * provider can be shown.
     *
     * @return list<string>
     */
    public function missing(Provider $provider): array
    {
        $config = $this->config($provider);

        return array_values(array_map(
            static fn (Field $field): string => $field->name,
            array_filter($provider->fields(), static fn (Field $field): bool => $field->required && ! $config->has($field->name)),
        ));
    }

    public function shown(Provider $provider): bool
    {
        return $this->enabled($provider) && $this->missing($provider) === [];
    }

    /** The provider by its key if it is shown, or null — a 404 for its routes. */
    public function find(string $key): ?Provider
    {
        $provider = $this->providers->find($key);

        return $provider !== null && $this->shown($provider) ? $provider : null;
    }

    /**
     * The providers shown, in the order of the registry: the buttons and the ways to sign in.
     *
     * @return list<Provider>
     */
    public function all(): array
    {
        return array_values(array_filter($this->providers->all(), fn (Provider $provider): bool => $this->shown($provider)));
    }

    /** After the panel saved: the next question reads the rows again. */
    public function forget(): void
    {
        $this->rows = null;
    }

    private function row(string $key): ?ProviderSettings
    {
        if ($this->rows === null) {
            $this->rows = $this->providers->keys() === []
                ? []
                : ProviderSettings::query()->whereIn('key', $this->providers->keys())->get()->keyBy('key')->all();
        }

        return $this->rows[$key] ?? null;
    }

    private function env(string $key, string $field): mixed
    {
        $configured = config('webx-users.social.keys.'.$key.'.'.$field);

        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        return Env::get(Str::upper('WEBX_USERS_'.str_replace('-', '_', $key).'_'.str_replace('-', '_', $field)));
    }
}
