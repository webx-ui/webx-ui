<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Categories;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;

/**
 * Entries offered to a block, the way a FAQ would offer its questions: filed under sections,
 * hidden when their name is empty, and remembering what it was last asked so a test can see the
 * choice a source is handed.
 */
final class EntrySource implements CollectionSource
{
    public ?Selection $asked = null;

    public function __construct(private readonly bool $markup = true) {}

    public function key(): string
    {
        return 'entries';
    }

    public function title(): string
    {
        return 'Entries';
    }

    public function categories(): string
    {
        return 'things/sections';
    }

    public function supportsMarkup(): bool
    {
        return $this->markup;
    }

    public function permission(): string
    {
        return 'things.view';
    }

    public function items(Selection $selection, string $locale): array
    {
        $this->asked = $selection;

        return $selection->apply(Entry::query()->where('name', '!=', '')->with('sections'))
            ->get()
            ->map(static fn (Entry $entry): array => [
                'id' => $entry->id,
                'anchor' => 'entry-'.$entry->id,
                'categories' => $entry->sections->modelKeys(),
                'name' => $entry->name,
            ])
            ->values()
            ->all();
    }
}
