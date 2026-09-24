<?php

declare(strict_types=1);

namespace WebxUi\Services\Rendering;

use ArrayIterator;
use Countable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use IteratorAggregate;
use Traversable;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Localization\Locales;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * `services()` — the services a template may show, as cards rather than models.
 *
 *     services()->in('implants')->take(6)          // one category, in its own order
 *     services()->in($category)                    // an editor's choice; empty is every service
 *     services()->only([12, 7, 30])                // these, in this order
 *     services()->except($service)->take(3)        // "other services" on a service page
 *     services()->categories()                     // the catalogue, grouped
 *
 * Every step returns a new query, so a template can keep one and branch it. What a reader may
 * see is not a step: published, out of the bin and written in the language being read — a
 * service without a slug in it has no address there, and a card that leads to a 404 is worse
 * than a shorter list. The shape of a card is {@see Cards}, the same one a `wx-collection` field
 * hands over, so a block can move between the two without its markup changing.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class ServiceQuery implements Countable, IteratorAggregate
{
    /**
     * @param  list<int|string>|null  $categories  Ids, or slugs in the query's language; null — no filter.
     * @param  list<int>|null  $only
     * @param  list<int>  $except
     */
    public function __construct(
        private readonly ?array $categories = null,
        private readonly ?array $only = null,
        private readonly array $except = [],
        private readonly ?int $limit = null,
        private readonly ?string $locale = null,
    ) {}

    /**
     * Only what is filed under these categories: an id, a slug, a category, or a list of them.
     *
     * Nothing — null, an empty string or list — is no filter at all, because that is what an
     * editor's untouched field sends and "every service" is what it means. A slug nobody has is
     * not nothing: it matches no service rather than all of them.
     *
     * @param  int|string|ServiceCategory|iterable<int|string|ServiceCategory>|null  $categories
     */
    public function in(int|string|ServiceCategory|iterable|null $categories): self
    {
        $given = [];

        foreach (is_iterable($categories) ? $categories : [$categories] as $category) {
            if ($category instanceof ServiceCategory) {
                $given[] = (int) $category->getKey();
            } elseif (is_int($category) || (is_string($category) && ctype_digit($category))) {
                $given[] = (int) $category;
            } elseif (is_string($category) && trim($category) !== '') {
                $given[] = trim($category);
            }
        }

        return new self($given === [] ? null : $given, $this->only, $this->except, $this->limit, $this->locale);
    }

    /**
     * These services and no others, in the order given — the order is the point of choosing.
     *
     * @param  int|Service|iterable<int|string|Service>  $services
     */
    public function only(int|Service|iterable $services): self
    {
        return new self($this->categories, $this->ids($services), $this->except, $this->limit, $this->locale);
    }

    /** @param  int|Service|iterable<int|string|Service>|null  $services */
    public function except(int|Service|iterable|null $services): self
    {
        $except = $services === null ? [] : $this->ids($services);

        return new self($this->categories, $this->only, [...$this->except, ...$except], $this->limit, $this->locale);
    }

    /** At most this many; null or zero — all of them. */
    public function take(int|string|null $limit): self
    {
        $limit = is_string($limit) && ctype_digit($limit) ? (int) $limit : $limit;

        return new self($this->categories, $this->only, $this->except, is_int($limit) && $limit > 0 ? $limit : null, $this->locale);
    }

    /** The language the cards are written in; by default, the one being rendered. */
    public function locale(?string $locale): self
    {
        return new self($this->categories, $this->only, $this->except, $this->limit, $locale);
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        $locale = $this->resolvedLocale();
        $categories = $this->categoryIds($locale);

        if ($categories === []) {
            return [];
        }

        $query = Service::query()->visible()->with(Cards::RELATIONS);

        if ($this->only !== null) {
            $query->whereIn('services.id', $this->only === [] ? [0] : $this->only);
        }

        if ($this->except !== []) {
            $query->whereNotIn('services.id', $this->except);
        }

        // No limit in SQL: whether a service is written in a language is a question of its slug,
        // and the limit counts what is shown.
        $query = (new Selection($categories ?? []))->apply($query);

        /** @var EloquentCollection<int, Service> $services */
        $services = $query->get();

        $services = $services->filter(static fn (Service $service): bool => $service->hasUrlIn($locale));

        if ($this->only !== null) {
            $order = array_flip($this->only);
            $services = $services->sortBy(static fn (Service $service): int => $order[(int) $service->getKey()] ?? PHP_INT_MAX);
        }

        if ($this->limit !== null) {
            $services = $services->take($this->limit);
        }

        return $this->cards()->services($services->values()->all(), $locale);
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        return $this->take(1)->get()[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->get() === [];
    }

    public function count(): int
    {
        return count($this->get());
    }

    /** @return Traversable<int, array<string, mixed>> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->get());
    }

    /**
     * The visible categories, in their order, each with the services it lists in its own order —
     * the catalogue as the index prints it. `in()` narrows the categories, `except()` and the
     * language apply to the services inside, and `take()` to each category rather than the
     * whole: a grouped list cut off after six is one category and a half.
     *
     * A category with nothing left to show is left out: a heading over nothing is not a group.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $locale = $this->resolvedLocale();
        $ids = $this->categoryIds($locale);

        if ($ids === []) {
            return [];
        }

        $query = ServiceCategory::query()
            ->visible()
            ->ordered()
            ->with([
                'cover',
                'routes',
                'services' => function (Relation $services): void {
                    $services->whereNotNull('services.published_at');

                    if ($this->except !== []) {
                        $services->whereNotIn('services.id', $this->except);
                    }

                    if ($this->only !== null) {
                        $services->whereIn('services.id', $this->only === [] ? [0] : $this->only);
                    }

                    $services->orderBy('service_category.item_position')
                        ->orderBy('services.position')
                        ->orderBy('services.id');
                },
                ...array_map(static fn (string $relation): string => 'services.'.$relation, Cards::RELATIONS),
            ]);

        if ($ids !== null) {
            $query->whereIn('service_categories.id', $ids);
        }

        /** @var EloquentCollection<int, ServiceCategory> $categories */
        $categories = $query->get();
        $groups = [];

        foreach ($categories as $category) {
            if (! $category->hasUrlIn($locale)) {
                continue;
            }

            $services = $category->services->filter(static fn (Service $service): bool => $service->hasUrlIn($locale));

            if ($this->limit !== null) {
                $services = $services->take($this->limit);
            }

            if ($services->isEmpty()) {
                continue;
            }

            $groups[] = $this->cards()->category($category, $services->values()->all(), $locale);
        }

        return $groups;
    }

    /**
     * The categories asked for as ids: null for no filter, and an empty list for a filter nothing
     * passes — a slug that names no category.
     *
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
            $found = ServiceCategory::query()
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
     * @param  int|string|Service|iterable<int|string|Service>  $services
     * @return list<int>
     */
    private function ids(int|string|Service|iterable $services): array
    {
        $ids = [];

        foreach (is_iterable($services) ? $services : [$services] as $service) {
            if ($service instanceof Service) {
                $ids[] = (int) $service->getKey();
            } elseif (is_int($service) || (is_string($service) && ctype_digit($service))) {
                $ids[] = (int) $service;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolvedLocale(): string
    {
        return $this->locale ?? Container::getInstance()->make(Locales::class)->current();
    }

    private function cards(): Cards
    {
        return Container::getInstance()->make(Cards::class);
    }
}
