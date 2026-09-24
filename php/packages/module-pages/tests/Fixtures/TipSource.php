<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests\Fixtures;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;

/**
 * Records a block can show, without categories and without markup: the plainest source there is,
 * so that what a save keeps of a `wx-collection` value is visibly what the source can act on.
 */
final class TipSource implements CollectionSource
{
    public function key(): string
    {
        return 'tips';
    }

    public function title(): string
    {
        return 'Tips';
    }

    public function categories(): ?string
    {
        return null;
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function items(Selection $selection, string $locale): array
    {
        return [['id' => 1, 'anchor' => 'water', 'categories' => [], 'text' => 'Drink water']];
    }
}
