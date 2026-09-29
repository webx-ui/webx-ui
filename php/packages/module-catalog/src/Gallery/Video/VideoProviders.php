<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Gallery\Video;

use LogicException;

/**
 * Every place a gallery video may come from, in the order they were registered (§3 of the video
 * spec) — the registry `Facets` is for filters. The core registers YouTube; the first provider
 * that recognises an address wins.
 */
final class VideoProviders
{
    /** What `video_provider` holds for a file of our own; no provider may take it. */
    public const FILE = 'file';

    private const KEY = '/^[a-z0-9][a-z0-9_-]{0,31}$/';

    /** @var array<string, VideoProvider> */
    private array $providers = [];

    public function register(VideoProvider $provider): void
    {
        $key = $provider->key();

        if (preg_match(self::KEY, $key) !== 1 || $key === self::FILE) {
            throw new LogicException("A video provider key is `[a-z0-9_-]`, at most 32 characters and not `file`; [{$key}] is not.");
        }

        $this->providers[$key] = $provider;
    }

    public function forget(string $key): void
    {
        unset($this->providers[$key]);
    }

    public function find(string $key): ?VideoProvider
    {
        return $this->providers[$key] ?? null;
    }

    /**
     * The provider and the id of the video behind an address; null when it is nobody's video.
     *
     * @return array{VideoProvider, string}|null
     */
    public function match(string $url): ?array
    {
        foreach ($this->providers as $provider) {
            $id = $provider->idFrom($url);

            if ($id !== null) {
                return [$provider, $id];
            }
        }

        return null;
    }

    /** @return list<VideoProvider> */
    public function all(): array
    {
        return array_values($this->providers);
    }
}
