<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use ArrayIterator;
use Countable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use IteratorAggregate;
use Traversable;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Localization\Locales;

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
 * Every step returns a new query. What a reader may see is not a step: published, out of the bin
 * and titled in the language being read. The order is the date — the nearest first among the ones
 * to come (those without a date before them), the latest first among the past ones — except with
 * `only()`, where the order given is the point. The shape of a card is {@see Cards}.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class EventQuery implements Countable, IteratorAggregate
{
    public const UPCOMING = 'upcoming';

    public const PAST = 'past';

    public const ALL = 'all';

    /**
     * @param  list<int|string>|null  $categories  Ids, or slugs in the query's language; null — no filter.
     * @param  array{type: string, ids: list<int>}|null  $related
     * @param  list<int>|null  $only
     * @param  list<int>  $except
     */
    public function __construct(
        private readonly string $when = self::UPCOMING,
        private readonly ?array $categories = null,
        private readonly ?array $related = null,
        private readonly ?array $only = null,
        private readonly array $except = [],
        private readonly ?int $limit = null,
        private readonly ?string $locale = null,
    ) {}

    /** The ones to come — what a query is until told otherwise. */
    public function upcoming(): self
    {
        return $this->with(['when' => self::UPCOMING]);
    }

    /** The ones that are over, the latest first (decision 6: never in the lists by themselves). */
    public function past(): self
    {
        return $this->with(['when' => self::PAST]);
    }

    /** Both, the ones without a date first and then the latest start first. */
    public function all(): self
    {
        return $this->with(['when' => self::ALL]);
    }

    /**
     * Only what is filed under these categories — any of them: an id, a slug, a category, or a
     * list. Nothing is no filter at all; a slug nobody has matches no event.
     *
     * @param  int|string|EventCategory|iterable<int|string|EventCategory>|null  $categories
     */
    public function in(int|string|EventCategory|iterable|null $categories): self
    {
        $given = [];

        foreach (is_iterable($categories) ? $categories : [$categories] as $category) {
            if ($category instanceof EventCategory) {
                $given[] = (int) $category->getKey();
            } elseif (is_int($category) || (is_string($category) && ctype_digit($category))) {
                $given[] = (int) $category;
            } elseif (is_string($category) && trim($category) !== '') {
                $given[] = trim($category);
            }
        }

        return $this->with(['categories' => $given === [] ? null : $given]);
    }

    /**
     * Only what is related to these records of another module — in any role. An empty list
     * matches nothing: "the events of no service" is not every event.
     *
     * @param  int|object|iterable<int|string|object>  $records
     */
    public function relatedTo(string $type, int|object|iterable $records): self
    {
        $ids = [];

        foreach (is_iterable($records) ? $records : [$records] as $record) {
            if (is_object($record) && method_exists($record, 'getKey')) {
                $ids[] = (int) $record->getKey();
            } elseif (is_int($record) || (is_string($record) && ctype_digit($record))) {
                $ids[] = (int) $record;
            }
        }

        return $this->with(['related' => ['type' => $type, 'ids' => array_values(array_unique($ids))]]);
    }

    /**
     * These events and no others, in the order given — past or to come alike.
     *
     * @param  int|Event|iterable<int|string|Event>  $events
     */
    public function only(int|Event|iterable $events): self
    {
        return $this->with(['only' => self::ids($events), 'when' => self::ALL]);
    }

    /** @param  int|Event|iterable<int|string|Event>|null  $events */
    public function except(int|Event|iterable|null $events): self
    {
        return $this->with(['except' => [...$this->except, ...($events === null ? [] : self::ids($events))]]);
    }

    /** At most this many; null or zero — all of them. */
    public function take(int|string|null $limit): self
    {
        $limit = is_string($limit) && ctype_digit($limit) ? (int) $limit : $limit;

        return $this->with(['limit' => is_int($limit) && $limit > 0 ? $limit : null]);
    }

    /** The language the cards are written in; by default, the one being rendered. */
    public function locale(?string $locale): self
    {
        return $this->with(['locale' => $locale]);
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        return Container::getInstance()->make(Cards::class)->events($this->models()->all(), $this->resolvedLocale());
    }

    /**
     * The events themselves, for code that needs the model: a list page counts them before it
     * builds cards for one page of them.
     *
     * @return EloquentCollection<int, Event>
     */
    public function models(): EloquentCollection
    {
        $locale = $this->resolvedLocale();
        $categories = $this->categoryIds($locale);

        if ($categories === [] || ($this->related !== null && $this->related['ids'] === [])) {
            return new EloquentCollection;
        }

        $query = Event::query()->visible()->with(Cards::RELATIONS);
        $model = $query->getModel();
        $key = $model->qualifyColumn($model->getKeyName());

        if ($this->only !== null) {
            $query->whereIn($key, $this->only === [] ? [0] : $this->only);
        }

        if ($this->except !== []) {
            $query->whereNotIn($key, $this->except);
        }

        if ($categories !== null) {
            $links = $model->categoryLinks();

            $query->whereIn($key, $links->newPivotStatement()
                ->select($links->getForeignPivotKeyName())
                ->whereIn($links->getRelatedPivotKeyName(), $categories));
        }

        if ($this->related !== null) {
            $query->whereIn($key, Relations::rows($model)
                ->select('owner_id')
                ->where('owner_type', $model->relationKey())
                ->where('target_type', $this->related['type'])
                ->whereIn('target_id', $this->related['ids']));
        }

        // Through `scopes()`: PHPStan finds no `upcoming()` on a builder of a model it only knows
        // as covariant (CLAUDE.md §4 on scopes over a generic builder).
        $query->scopes(match ($this->when) {
            self::PAST => ['past'],
            self::ALL => ['byDate'],
            default => ['upcoming'],
        });

        /** @var EloquentCollection<int, Event> $events */
        $events = $query->get();

        // No limit in SQL: whether an event is written in a language is a question of its title
        // and its slug, and the limit counts what is shown.
        $events = $events->filter(static fn (Event $event): bool => $event->hasUrlIn($locale) && $event->isVisible($locale));

        if ($this->only !== null) {
            $order = array_flip($this->only);
            $events = $events->sortBy(static fn (Event $event): int => $order[(int) $event->getKey()] ?? PHP_INT_MAX);
        }

        if ($this->limit !== null) {
            $events = $events->take($this->limit);
        }

        return $events->values();
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        return $this->take(1)->get()[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->models()->isEmpty();
    }

    public function count(): int
    {
        return $this->models()->count();
    }

    /** @return Traversable<int, array<string, mixed>> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->get());
    }

    public function resolvedLocale(): string
    {
        return $this->locale !== null
            ? $this->locale
            : Container::getInstance()->make(Locales::class)->current();
    }

    /**
     * A copy with some of the steps changed — by name, so a step set to null ("no filter") is
     * told apart from a step not mentioned.
     *
     * @param  array<string, mixed>  $changes
     */
    private function with(array $changes): self
    {
        $steps = [
            'when' => $this->when,
            'categories' => $this->categories,
            'related' => $this->related,
            'only' => $this->only,
            'except' => $this->except,
            'limit' => $this->limit,
            'locale' => $this->locale,
        ];

        /** @var array{when: string, categories: list<int|string>|null, related: array{type: string, ids: list<int>}|null, only: list<int>|null, except: list<int>, limit: int|null, locale: string|null} $steps */
        $steps = [...$steps, ...$changes];

        return new self(...$steps);
    }

    /**
     * @return list<int>|null
     */
    private function categoryIds(string $locale): ?array
    {
        if ($this->categories === null) {
            return null;
        }

        $ids = [];
        $slugs = [];

        foreach ($this->categories as $category) {
            is_int($category) ? $ids[] = $category : $slugs[] = $category;
        }

        if ($slugs !== []) {
            $found = EventCategory::query()
                ->where(static function (Builder $query) use ($slugs, $locale): void {
                    foreach ($slugs as $slug) {
                        $query->orWhere('slug->'.$locale, $slug);
                    }
                })
                ->pluck('id')
                ->map(intval(...))
                ->all();

            $ids = [...$ids, ...$found];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  int|string|Event|iterable<int|string|Event>  $records
     * @return list<int>
     */
    private static function ids(int|string|Event|iterable $records): array
    {
        $ids = [];

        foreach (is_iterable($records) ? $records : [$records] as $record) {
            if ($record instanceof Event) {
                $ids[] = (int) $record->getKey();
            } elseif (is_int($record) || (is_string($record) && ctype_digit($record))) {
                $ids[] = (int) $record;
            }
        }

        return array_values(array_unique($ids));
    }
}
