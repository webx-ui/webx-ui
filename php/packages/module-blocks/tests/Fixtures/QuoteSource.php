<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests\Fixtures;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;

/**
 * Records a block can show, with no table behind them: what a module's source looks like from
 * the renderer's side. Remembers the last choice it was handed.
 */
final class QuoteSource implements CollectionSource
{
    public ?Selection $asked = null;

    public function key(): string
    {
        return 'quotes';
    }

    public function title(): string
    {
        return 'Quotes';
    }

    public function categories(): ?string
    {
        return null;
    }

    public function supportsMarkup(): bool
    {
        return true;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function items(Selection $selection, string $locale): array
    {
        $this->asked = $selection;

        $quotes = [
            ['id' => 1, 'anchor' => 'brevity', 'categories' => [], 'words' => 'Brevity is the soul of wit'],
            ['id' => 2, 'anchor' => 'less', 'categories' => [], 'words' => 'Less is more'],
            ['id' => 3, 'anchor' => 'enough', 'categories' => [], 'words' => 'Enough said'],
        ];

        return $selection->limit === null ? $quotes : array_slice($quotes, 0, $selection->limit);
    }
}
