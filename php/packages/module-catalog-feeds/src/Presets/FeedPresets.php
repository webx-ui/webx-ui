<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Presets;

use InvalidArgumentException;

/**
 * The presets a new feed is offered, in the order they were registered. A module or a site adds its
 * own after this one resolves; a taken key is a mistake of the code, not of a person.
 */
final class FeedPresets
{
    /** @var array<string, FeedPreset> */
    private array $presets = [];

    /**
     * @param  list<FeedPreset>  $presets
     */
    public function __construct(array $presets = [])
    {
        foreach ($presets as $preset) {
            $this->register($preset);
        }
    }

    public function register(FeedPreset $preset): void
    {
        if (isset($this->presets[$preset->key()])) {
            throw new InvalidArgumentException("The feed preset [{$preset->key()}] is already registered.");
        }

        $this->presets[$preset->key()] = $preset;
    }

    public function find(string $key): ?FeedPreset
    {
        return $this->presets[$key] ?? null;
    }

    /**
     * @return list<FeedPreset>
     */
    public function all(): array
    {
        return array_values($this->presets);
    }
}
