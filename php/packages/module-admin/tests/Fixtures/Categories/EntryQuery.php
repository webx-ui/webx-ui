<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Categories;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Admin\Collections\RecordQuery;

/**
 * Entries as {@see EntrySource} shows them: hidden when their name is empty.
 *
 * @extends RecordQuery<Entry>
 */
final class EntryQuery extends RecordQuery
{
    protected function newQuery(string $locale): Builder
    {
        return Entry::query()->where('name', '!=', '')->with('sections');
    }

    protected function cards(array $records, string $locale): array
    {
        return array_map(static fn (Entry $entry): array => [
            'id' => $entry->id,
            'anchor' => 'entry-'.$entry->id,
            'categories' => $entry->sections->modelKeys(),
            'name' => $entry->name,
        ], $records);
    }
}
