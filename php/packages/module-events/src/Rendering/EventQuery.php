<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;

/**
 * `events()` — the events a template may show, as cards rather than models (§4.8).
 *
 *     events()->take(3)                              // the next three
 *     events()->past()->take(6)                      // the last six that are over
 *     events()->in('cooking-classes')                // one category
 *     events()->relatedTo('service', $service)       // "the events of this service"
 *     events()->only([12, 7, 30])                    // these, in this order
 *     events()->except($event)->take(3)
 *
 * What a reader may see is not a step: published, out of the bin and titled in the language being
 * read. The order is the date — the nearest first among the ones to come (those without a date
 * before them), the latest first among the past ones — except with `only()`, where the order
 * given is the point. The shape of a card is {@see Cards}. The steps themselves and their rules
 * are {@see RecordQuery}'s.
 *
 * @extends RecordQuery<Event>
 */
final class EventQuery extends RecordQuery
{
    public const UPCOMING = 'upcoming';

    public const PAST = 'past';

    public const ALL = 'all';

    /** The ones to come — what a query is until told otherwise. */
    public function upcoming(): self
    {
        return $this->withStep('when', self::UPCOMING);
    }

    /** The ones that are over, the latest first (decision 6: never in the lists by themselves). */
    public function past(): self
    {
        return $this->withStep('when', self::PAST);
    }

    /** Both, the ones without a date first and then the latest start first. */
    public function all(): self
    {
        return $this->withStep('when', self::ALL);
    }

    /**
     * Only what is filed under these categories — any of them: an id, a slug, a category, or a
     * list. Nothing is no filter at all; a slug nobody has matches no event.
     *
     * @param  int|string|EventCategory|iterable<int|string|EventCategory>|null  $categories
     */
    public function in(int|string|EventCategory|iterable|null $categories): self
    {
        return $this->withCategories($categories);
    }

    /**
     * Only what is related to these records of another module — in any role. An empty list
     * matches nothing: "the events of no service" is not every event.
     *
     * @param  int|object|iterable<int|string|object>  $records
     */
    public function relatedTo(string $type, int|object|iterable $records): self
    {
        return $this->withRelated($type, $records);
    }

    protected function newQuery(string $locale): Builder
    {
        return Event::query()->visible()->with(Cards::RELATIONS);
    }

    /** Titled and with an address in the language: the title and the slug are words. */
    protected function shownIn(Model $record, string $locale): bool
    {
        return $record->hasUrlIn($locale) && $record->isVisible($locale);
    }

    protected function categoryModel(): string
    {
        return EventCategory::class;
    }

    /** The date, whatever the category: events have no order of their own. */
    protected function order(Builder $query, ?int $category): void
    {
        // Through `scopes()`: PHPStan finds no `upcoming()` on a builder of a model it only knows
        // as covariant (CLAUDE.md §4 on scopes over a generic builder).
        $query->scopes(match ($this->step('when', self::UPCOMING)) {
            self::PAST => ['past'],
            self::ALL => ['byDate'],
            default => ['upcoming'],
        });
    }

    protected function cards(array $records, string $locale): array
    {
        return Container::getInstance()->make(Cards::class)->events($records, $locale);
    }
}
