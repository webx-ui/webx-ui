<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Site;

use WebxUi\Cache\CacheGroups;
use WebxUi\Configurator\Models\Configurator;

/**
 * The configurators the site shows, by key (§12.6): what is published, read once per version of
 * the `configurators` group — a page with the block asks the database only after somebody
 * published, unpublished or binned one.
 *
 * Kept as an array rather than the model: the cache stores what it can serialise plainly, and
 * nothing on the site needs more than this.
 */
final class LiveConfigurators
{
    public const string GROUP = 'configurators';

    public function __construct(private readonly CacheGroups $cache) {}

    /**
     * The published configurator with this key, or null — one in the bin, never published or
     * taken off the site is nothing to the site.
     *
     * @return array{id: int, key: string, title: array<string, string>, schema: array<string, mixed>, form: string|null, version: int|null}|null
     */
    public function find(string $key): ?array
    {
        $key = trim($key);

        if ($key === '') {
            return null;
        }

        $found = $this->cache->remember('configurators.live.'.md5($key), [self::GROUP], static function () use ($key): array|false {
            $configurator = Configurator::query()->where('key', $key)->first();
            $schema = $configurator?->liveSchema();

            if (! $configurator instanceof Configurator || $schema === null) {
                // Not a null: the cache keeps no null, and a page with a block of a configurator
                // nobody published would ask the database on every view.
                return false;
            }

            return [
                'id' => (int) $configurator->getKey(),
                'key' => $configurator->key,
                'title' => $configurator->getTranslations('title'),
                'schema' => $schema,
                'form' => is_string($configurator->form) && $configurator->form !== '' ? $configurator->form : null,
                'version' => $configurator->liveVersion(),
            ];
        }, localized: false);

        return is_array($found) ? $found : null;
    }
}
