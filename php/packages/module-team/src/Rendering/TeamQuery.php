<?php

declare(strict_types=1);

namespace WebxUi\Team\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Team\Models\Member;

/**
 * `team()` — the people a template may show, as cards rather than models (§5.3 of the team spec).
 *
 *     team()->take(6)                              // the first six, in the order of the list
 *     team()->relatedTo('service', $service)       // who provides this service
 *     team()->only([12, 7])                        // these, in this order
 *     team()->except($member)                      // all but these
 *
 * The steps and their meaning are those of `services()` and `reviews()` — all of them are a
 * {@see RecordQuery}. No `in()` and no `categories()`: the team has no categories (decision 1).
 * Who a reader may see is not a step: published and out of the bin, in every language
 * (decision 8). The shape of a card is {@see Cards}, the same one a `wx-collection` field hands over.
 *
 * @extends RecordQuery<Member>
 */
final class TeamQuery extends RecordQuery
{
    /**
     * Only the people related to these records of another module: `relatedTo('service', 3)`.
     * An empty list is nobody — "the people of no service" is not the whole team.
     *
     * @param  int|object|iterable<int|string|object>  $records
     */
    public function relatedTo(string $type, int|object|iterable $records): self
    {
        return $this->withRelated($type, $records);
    }

    protected function newQuery(string $locale): Builder
    {
        return Member::query()->visible();
    }

    /**
     * @param  list<Member>  $records
     * @return list<array<string, mixed>>
     */
    protected function cards(array $records, string $locale): array
    {
        return Container::getInstance()->make(Cards::class)->members($records, $locale);
    }

    /**
     * Nobody is hidden over a language (decision 8) — said here in so many words, so that a later
     * change to the default does not quietly start hiding people.
     */
    protected function shownIn(Model $record, string $locale): bool
    {
        return true;
    }
}
