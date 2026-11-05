<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Formats;

use InvalidArgumentException;
use WebxUi\CatalogFeeds\Build\FeedFailure;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Presets\FeedPresets;

/**
 * The formats written by a PHP class (§10 of the feeds spec), registered from a site's provider. A
 * format is offered as a preset of its own: a feed gets its `driver` from what a person chose among
 * the code's registrations, never from what a form sent.
 *
 * A bad key is a mistake of the code, not of a person, so it stops the boot.
 */
final class FeedFormats
{
    /** `preset` of a feed holds it too, and that column is the shorter one. */
    private const KEY = '/^[a-z0-9][a-z0-9_-]{0,31}$/';

    private const EXTENSION = '/^[a-z0-9]+$/';

    /** @var array<string, FeedFormat> */
    private array $formats = [];

    public function __construct(private readonly FeedPresets $presets) {}

    public function register(FeedFormat $format): void
    {
        $key = $format->key();

        if (preg_match(self::KEY, $key) !== 1) {
            throw new InvalidArgumentException("The feed format key [{$key}] is not `a-z`, digits, `-` and `_` up to 32 characters.");
        }

        if (preg_match(self::EXTENSION, $format->extension()) !== 1) {
            throw new InvalidArgumentException("The feed format [{$key}] has the extension [{$format->extension()}]: an address takes `a-z` and digits.");
        }

        if (isset($this->formats[$key])) {
            throw new InvalidArgumentException("The feed format [{$key}] is already registered.");
        }

        $this->presets->register(new FormatPreset($format));
        $this->formats[$key] = $format;
    }

    public function find(string $key): ?FeedFormat
    {
        return $this->formats[$key] ?? null;
    }

    /**
     * What writes this feed: null for its template.
     *
     * @throws FeedFailure the class is gone, and the file served stays
     */
    public function of(Feed $feed): ?FeedFormat
    {
        if ($feed->driver === null) {
            return null;
        }

        return $this->find($feed->driver) ?? throw FeedFailure::of('format-gone', ['driver' => $feed->driver]);
    }

    /**
     * @return list<FeedFormat>
     */
    public function all(): array
    {
        return array_values($this->formats);
    }
}
