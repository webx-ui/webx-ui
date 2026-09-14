<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Settings\Events\SettingsSaved;
use WebxUi\Settings\Models\Setting;

/**
 * The settings, for the site and for the panel.
 *
 * `raw()` is the table as stored, cached whole; `get()` is what a template wants — the current
 * language of a localized value, a media key turned into an address — decided by the field's
 * type in the screen. A key the screen does not describe is still read back as stored: a
 * project patch that added a field the server has no type for gets its value, unchanged.
 */
final class Settings
{
    public const SCREEN = 'settings.index';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function __construct(
        private readonly ScreenRegistry $screens,
        private readonly ScreenValues $values,
        private readonly Cache $cache,
        private readonly Config $config,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Everything stored, keyed, as stored.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $load = static fn (): array => Setting::query()->pluck('value', 'key')->all();

        $this->loaded = $this->cacheEnabled()
            ? $this->cache->remember($this->cacheKey(), (int) $this->config->get('webx-settings.cache.ttl', 86400), $load)
            : $load();

        return $this->loaded;
    }

    /**
     * The keys the screen describes — the ones that can be edited and written.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_map(
            static fn (array $node): string => (string) $node['name'],
            $this->screens->fields(self::SCREEN),
        ));
    }

    /** One value, the way the site reads it. */
    public function get(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $raw = $this->raw();

        if (! array_key_exists($key, $raw)) {
            return $default;
        }

        $node = $this->node($key);
        $value = $node === null ? $raw[$key] : $this->values->resolve($node, $raw[$key], $locale);

        return $value ?? $default;
    }

    /**
     * Every described value, resolved.
     *
     * @return array<string, mixed>
     */
    public function all(?string $locale = null): array
    {
        return $this->values->resolveAll(self::SCREEN, $this->raw(), $locale);
    }

    /**
     * Writes what it is given — already validated by `ScreenValues` — and tells the site.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->forget();
        $this->events->dispatch(new SettingsSaved(array_keys($values)));
    }

    public function forget(): void
    {
        $this->loaded = null;

        if ($this->cacheEnabled()) {
            $this->cache->forget($this->cacheKey());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function node(string $key): ?array
    {
        foreach (Tree::fields($this->screens->tree(self::SCREEN)) as $node) {
            if ($node['name'] === $key) {
                return $node;
            }
        }

        return null;
    }

    private function cacheEnabled(): bool
    {
        return (bool) $this->config->get('webx-settings.cache.enabled', true);
    }

    private function cacheKey(): string
    {
        return (string) $this->config->get('webx-settings.cache.key', 'webx.settings');
    }
}
