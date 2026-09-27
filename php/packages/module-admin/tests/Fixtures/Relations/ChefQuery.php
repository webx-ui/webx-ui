<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;

/**
 * A record query over a model without categories — what a team is: one order, relations only.
 * A chef "is written in" a language when the title ends with its code, so the language filter
 * and the limit after it have something to tell apart.
 *
 * @extends RecordQuery<Chef>
 */
final class ChefQuery extends RecordQuery
{
    /** @param  int|object|iterable<int|string|object>  $records */
    public function relatedTo(string $type, int|object|iterable $records): self
    {
        return $this->withRelated($type, $records);
    }

    protected function newQuery(string $locale): Builder
    {
        return Chef::query()->where('retired', false);
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        return ! str_ends_with((string) $record->getAttribute('title'), '-'.($locale === 'en' ? 'ru' : 'en'));
    }

    protected function cards(array $records, string $locale): array
    {
        return array_map(static fn (Chef $chef): array => ['id' => (int) $chef->getKey(), 'title' => $chef->getAttribute('title')], $records);
    }
}
