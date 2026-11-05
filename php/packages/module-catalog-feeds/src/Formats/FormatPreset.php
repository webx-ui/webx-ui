<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Formats;

use WebxUi\CatalogFeeds\Presets\FeedPreset;

/**
 * A format as «New feed» offers it. `text` is only what the column has to hold: the class decides
 * the extension, the type and the check, and nothing of a template applies.
 */
final readonly class FormatPreset implements FeedPreset
{
    public function __construct(private FeedFormat $format) {}

    public function key(): string
    {
        return $this->format->key();
    }

    public function name(): string
    {
        return $this->format->label();
    }

    public function description(): string
    {
        return $this->format::class;
    }

    public function values(): array
    {
        return ['format' => 'text', 'item' => '', 'driver' => $this->format->key()];
    }
}
