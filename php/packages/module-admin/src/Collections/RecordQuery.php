<?php

declare(strict_types=1);

namespace WebxUi\Admin\Collections;

use ArrayIterator;
use Countable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Localization\Locales;

/**
 * What a site helper like `services()` or `reviews()` is made of: the records a template may
 * show, as cards rather than models, narrowed step by step.
 *
 *     reviews()->in($categories)->take(6)       // an editor's choice; empty is everything
 *     recipes()->relatedTo('service', $service) // "the recipes of this service"
 *     services()->only([12, 7])                 // these, in this order
 *     services()->except($service)->take(3)
 *
 * Every step returns a new query, so a template can keep one and branch it. What a reader may see
 * is not a step but the module's rule ({@see newQuery()}, {@see shownIn()}), and it holds the same
 * way for every module, which is the point of one class:
 *
 * - an untouched filter is no filter — an editor's empty field means "everything"; a filter that
 *   named something and found nothing is a filter nothing passes, so a typo in a slug does not
 *   quietly become every record there is. The same for `relatedTo()` with no records;
 * - the limit counts what is shown, after the language: "the first six" on a Russian page is six
 *   Russian ones, so it is taken in php, not in SQL;
 * - `only()` sets the order, and it wins over any other.
 *
 * A module extends this with its model, its visibility and its cards; the steps it has and others
 * have not (categories, relations, `upcoming()`) it opens as its own public methods over the
 * protected ones here, typed with its own models.
 *
 * @template TModel of Model
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
abstract class RecordQuery implements Countable, IteratorAggregate
{
    /** @var list<int|string>|null Ids, or slugs in the query's language; null — no filter. */
    private ?array $categories = null;

    /** @var array{type: string, ids: list<int>}|null */
    private ?array $related = null;

    /** @var list<int>|null */
    private ?array $only = null;

    /** @var list<int> */
    private array $except = [];

    private ?int $limit = null;

    private ?string $locale = null;

    /**
     * The module's own steps (`when` of events, `nutrients` of recipes), read with {@see step()}.
     *
     * @var array<string, mixed>
     */
    private array $steps = [];

    /**
     * The records a reader may see, as SQL can tell: published, out of the bin, loaded with what
     * the cards need. What only the words can tell is {@see shownIn()}.
     *
     * Covariant, because a query may list more than one kind of record (`press()`: outlets or
     * articles) and only reads the builder it gets.
     *
     * @return Builder<covariant TModel>
     */
    abstract protected function newQuery(string $locale): Builder;

    /**
     * @param  list<TModel>  $records
     * @return list<array<string, mixed>>
     */
    abstract protected function cards(array $records, string $locale): array;

    /**
     * Whether a record is written in the language — a question of its words, answered in php.
     *
     * @param  TModel  $record
     */
    protected function shownIn(Model $record, string $locale): bool
    {
        return true;
    }

    /**
     * The model a category slug names, or null when categories are named by id only. A slug is
     * looked up in the query's language.
     *
     * @return class-string<Model>|null
     */
    protected function categoryModel(): ?string
    {
        return null;
    }

    /**
     * The order. `$category` is the one category asked for, when exactly one was: a module whose
     * categories keep an order of their own lists it in that one. By default `orderedIn` —
     * a category's order or the global one — and `position`, `id` for a model without categories.
     *
     * @param  Builder<covariant TModel>  $query
     */
    protected function order(Builder $query, ?int $category): void
    {
        $model = $query->getModel();

        if (method_exists($model, 'scopeOrderedIn')) {
            // Through `scopes()`: PHPStan finds no `orderedIn()` on a builder of a generic model.
            $query->scopes(['orderedIn' => [$category]]);

            return;
        }

        $query->orderBy($model->qualifyColumn('position'))->orderBy($model->qualifyColumn($model->getKeyName()));
    }

    /**
     * The module's own filters over what the steps here already did.
     *
     * @param  Builder<covariant TModel>  $query
     */
    protected function narrow(Builder $query, string $locale): void {}

    /**
     * These records and no others, in the order given — the order is the point of choosing.
     *
     * @param  int|string|Model|iterable<int|string|Model>  $records
     */
    public function only(int|string|Model|iterable $records): static
    {
        return $this->with(static function (self $query) use ($records): void {
            $query->only = self::ids($records);
        });
    }

    /** @param  int|string|Model|iterable<int|string|Model>|null  $records */
    public function except(int|string|Model|iterable|null $records): static
    {
        return $this->with(static function (self $query) use ($records): void {
            $query->except = [...$query->except, ...($records === null ? [] : self::ids($records))];
        });
    }

    /**
     * What an editor chose in a `wx-collection` field — its categories, its relation and its
     * limit — so that a source is this and a language, and who may be seen stays the helper's
     * rule. What the source cannot do the choice no longer holds ({@see Selection::of()}).
     */
    public function selected(Selection $selection): static
    {
        $query = $this->withCategories($selection->categories)->take($selection->limit);
        $related = $selection->related();

        return $related === null ? $query : $query->withRelated($related['type'], $related['ids']);
    }

    /** At most this many; null or zero — all of them. */
    public function take(int|string|null $limit): static
    {
        $limit = is_string($limit) && ctype_digit($limit) ? (int) $limit : $limit;

        return $this->with(static function (self $query) use ($limit): void {
            $query->limit = is_int($limit) && $limit > 0 ? $limit : null;
        });
    }

    /** The language the cards are written in; by default, the one being rendered. */
    public function locale(?string $locale): static
    {
        return $this->with(static function (self $query) use ($locale): void {
            $query->locale = $locale;
        });
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        return $this->cards(array_values($this->models()->all()), $this->resolvedLocale());
    }

    /**
     * The records themselves, for code that needs the model: a list page counts them before it
     * builds cards for one page of them.
     *
     * @return EloquentCollection<int, TModel>
     */
    public function models(): EloquentCollection
    {
        $locale = $this->resolvedLocale();
        $categories = $this->categoryIds($locale);

        if ($categories === [] || ($this->related !== null && $this->related['ids'] === [])) {
            return new EloquentCollection;
        }

        $query = $this->newQuery($locale);
        $model = $query->getModel();
        $key = $model->qualifyColumn($model->getKeyName());

        // A caller's mistake, not a filter: listing everything instead would be the one answer
        // sure to be wrong. A source without categories never hands any ({@see Selection::of()}).
        if ($categories !== null && ! method_exists($model, 'categoryLinks')) {
            throw new InvalidArgumentException($model::class.' is not filed under categories.');
        }

        if ($this->only !== null) {
            $query->whereIn($key, $this->only === [] ? [0] : $this->only);
        }

        if ($this->except !== []) {
            $query->whereNotIn($key, $this->except);
        }

        // `method_exists` again only so that PHPStan knows the method is there.
        if ($categories !== null && method_exists($model, 'categoryLinks')) {
            $links = $model->categoryLinks();

            $query->whereIn($key, $links->newPivotStatement()
                ->select($links->getForeignPivotKeyName())
                ->whereIn($links->getRelatedPivotKeyName(), $categories));
        }

        if ($this->related !== null && method_exists($model, 'relationKey')) {
            $query->whereIn($key, Relations::rows($model)
                ->select('owner_id')
                ->where('owner_type', $model->relationKey())
                ->where('target_type', $this->related['type'])
                ->whereIn('target_id', $this->related['ids']));
        }

        $this->narrow($query, $locale);
        $this->order($query, $categories !== null && count($categories) === 1 ? $categories[0] : null);

        // No limit in SQL: whether a record is written in a language is a question of its words,
        // and the limit counts what is shown.
        $records = [];

        foreach ($query->get() as $record) {
            if ($this->shownIn($record, $locale)) {
                $records[] = $record;
            }
        }

        if ($this->only !== null) {
            $order = array_flip($this->only);
            usort($records, static fn (Model $a, Model $b): int => ($order[(int) $a->getKey()] ?? PHP_INT_MAX) <=> ($order[(int) $b->getKey()] ?? PHP_INT_MAX));
        }

        if ($this->limit !== null) {
            $records = array_slice($records, 0, $this->limit);
        }

        return new EloquentCollection($records);
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
        return $this->locale ?? Container::getInstance()->make(Locales::class)->current();
    }

    /**
     * Only what is filed under these categories — any of them: an id, a slug, a category, or a
     * list. Nothing — null, an empty string or list — is no filter at all.
     *
     * @param  int|string|Model|iterable<int|string|Model>|null  $categories
     */
    protected function withCategories(int|string|Model|iterable|null $categories): static
    {
        $given = [];

        foreach (is_iterable($categories) ? $categories : [$categories] as $category) {
            if ($category instanceof Model) {
                $given[] = (int) $category->getKey();
            } elseif (is_int($category) || (is_string($category) && ctype_digit($category))) {
                $given[] = (int) $category;
            } elseif (is_string($category) && trim($category) !== '') {
                $given[] = trim($category);
            }
        }

        return $this->with(static function (self $query) use ($given): void {
            $query->categories = $given === [] ? null : $given;
        });
    }

    /**
     * Only what is related to these records of another module — in any role. An empty list
     * matches nothing: "the recipes of no service" is not every recipe.
     *
     * @param  int|object|iterable<int|string|object>  $records
     */
    protected function withRelated(string $type, int|object|iterable $records): static
    {
        $ids = [];

        foreach (is_iterable($records) ? $records : [$records] as $record) {
            if (is_object($record) && method_exists($record, 'getKey')) {
                $ids[] = (int) $record->getKey();
            } elseif (is_int($record) || (is_string($record) && ctype_digit($record))) {
                $ids[] = (int) $record;
            }
        }

        $related = ['type' => $type, 'ids' => array_values(array_unique($ids))];

        return $this->with(static function (self $query) use ($related): void {
            $query->related = $related;
        });
    }

    /** A copy with one of the module's own steps set. */
    protected function withStep(string $name, mixed $value): static
    {
        return $this->with(static function (self $query) use ($name, $value): void {
            $query->steps[$name] = $value;
        });
    }

    /** One of the module's own steps, or `$default` when it was never set. */
    protected function step(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->steps) ? $this->steps[$name] : $default;
    }

    /**
     * The categories asked for as ids: null for no filter, and an empty list for a filter nothing
     * passes — a slug that names no category, or a name where categories have no slugs.
     *
     * @return list<int>|null
     */
    protected function categoryIds(string $locale): ?array
    {
        if ($this->categories === null) {
            return null;
        }

        $ids = [];
        $slugs = [];

        foreach ($this->categories as $category) {
            is_int($category) ? $ids[] = $category : $slugs[] = $category;
        }

        $model = $this->categoryModel();

        if ($slugs !== [] && $model !== null) {
            $found = $model::query()
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

    /** @return list<int>|null */
    protected function onlyIds(): ?array
    {
        return $this->only;
    }

    /** @return list<int> */
    protected function exceptIds(): array
    {
        return $this->except;
    }

    protected function limit(): ?int
    {
        return $this->limit;
    }

    /** @param  callable(self<TModel>): void  $change */
    private function with(callable $change): static
    {
        $copy = clone $this;
        $change($copy);

        return $copy;
    }

    /**
     * @param  int|string|Model|iterable<int|string|Model>  $records
     * @return list<int>
     */
    private static function ids(int|string|Model|iterable $records): array
    {
        $ids = [];

        foreach (is_iterable($records) ? $records : [$records] as $record) {
            if ($record instanceof Model) {
                $ids[] = (int) $record->getKey();
            } elseif (is_int($record) || (is_string($record) && ctype_digit($record))) {
                $ids[] = (int) $record;
            }
        }

        return array_values(array_unique($ids));
    }
}
