<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Presets;

/**
 * A ready template to start a feed from (§9 of the feeds spec). Its values are copied into the feed
 * when the feed is created and nothing ties them afterwards: a new release of a preset leaves the
 * feeds made from it as they are.
 */
interface FeedPreset
{
    /** What `preset` of a feed holds, up to 32 characters. */
    public function key(): string;

    /** What a new feed is called until a person names it. */
    public function name(): string;

    /** One line for the dialog that offers the presets. */
    public function description(): string;

    /**
     * The fields of `FeedForm`, at least `format` and `item`.
     *
     * @return array<string, mixed>
     */
    public function values(): array;
}
