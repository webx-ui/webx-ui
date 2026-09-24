<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use ArrayIterator;
use Countable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use IteratorAggregate;
use Traversable;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Localization\Locales;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;

/**
 * `recipes()` — the recipes a template may show, as cards rather than models.
 *
 *     recipes()->in('breakfasts')->take(6)             // one category
 *     recipes()->nutrients([3])                        // rich in iron
 *     recipes()->relatedTo('service', $service)        // "the recipes of this service"
 *     recipes()->only([12, 7, 30])                     // these, in this order
 *     recipes()->except($recipe)->take(3)
 *
 * Every step returns a new query. What a reader may see is not a step: published, out of the bin
 * and written in the language being read. The order is the one order recipes have (decision 4),
 * except with `only()`, where the order given is the point. The shape of a card is {@see Cards},
 * the same one a `wx-collection` field hands over.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class RecipeQuery implements Countable, IteratorAggregate
{
    /**
     * @param  list<int|string>|null  $categories  Ids, or slugs in the query's language; null — no filter.
     * @param  list<int>|null  $nutrients
     * @param  array{type: string, ids: list<int>}|null  $related
     * @param  list<int>|null  $only
     * @param  list<int>  $except
     */
    public function __construct(
        private readonly ?array $categories = null,
        private readonly ?array $nutrients = null,
        private readonly ?array $related = null,
        private readonly ?array $only = null,
        private readonly array $except = [],
        private readonly ?int $limit = null,
        private readonly ?string $locale = null,
    ) {}

    /**
     * Only what is filed under these categories — any of them: an id, a slug, a category, or a
     * list. Nothing is no filter at all; a slug nobody has matches no recipe.
     *
     * @param  int|string|RecipeCategory|iterable<int|string|RecipeCategory>|null  $categories
     */
    public function in(int|string|RecipeCategory|iterable|null $categories): self
    {
        $given = [];

        foreach (is_iterable($categories) ? $categories : [$categories] as $category) {
            if ($category instanceof RecipeCategory) {
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
     * Only what is rich in any of these. Nothing is no filter.
     *
     * @param  int|iterable<int|string>|null  $nutrients
     */
    public function nutrients(int|iterable|null $nutrients): self
    {
        $ids = $nutrients === null ? [] : self::ids($nutrients);

        return $this->with(['nutrients' => $ids === [] ? null : $ids]);
    }

    /**
     * Only what is related to these records of another module — in any role. An empty list
     * matches nothing: "the recipes of no service" is not every recipe.
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
     * These recipes and no others, in the order given.
     *
     * @param  int|Recipe|iterable<int|string|Recipe>  $recipes
     */
    public function only(int|Recipe|iterable $recipes): self
    {
        return $this->with(['only' => self::ids($recipes)]);
    }

    /** @param  int|Recipe|iterable<int|string|Recipe>|null  $recipes */
    public function except(int|Recipe|iterable|null $recipes): self
    {
        return $this->with(['except' => [...$this->except, ...($recipes === null ? [] : self::ids($recipes))]]);
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
        return Container::getInstance()->make(Cards::class)->recipes($this->models()->all(), $this->resolvedLocale());
    }

    /**
     * The recipes themselves, for code that needs the model: the catalogue counts them before it
     * builds cards for one page of them.
     *
     * @return EloquentCollection<int, Recipe>
     */
    public function models(): EloquentCollection
    {
        $locale = $this->resolvedLocale();
        $categories = $this->categoryIds($locale);

        if ($categories === [] || ($this->related !== null && $this->related['ids'] === [])) {
            return new EloquentCollection;
        }

        $query = Recipe::query()->visible()->with(Cards::RELATIONS);
        $model = $query->getModel();
        $key = $model->qualifyColumn($model->getKeyName());

        if ($this->only !== null) {
            $query->whereIn($key, $this->only === [] ? [0] : $this->only);
        }

        if ($this->except !== []) {
            $query->whereNotIn($key, $this->except);
        }

        foreach (['categories' => $categories, 'nutrients' => $this->nutrients] as $relation => $ids) {
            if ($ids === null) {
                continue;
            }

            $links = $model->categoryLinks($relation);

            $query->whereIn($key, $links->newPivotStatement()
                ->select($links->getForeignPivotKeyName())
                ->whereIn($links->getRelatedPivotKeyName(), $ids));
        }

        if ($this->related !== null) {
            $query->whereIn($key, Relations::rows($model)
                ->select('owner_id')
                ->where('owner_type', $model->relationKey())
                ->where('target_type', $this->related['type'])
                ->whereIn('target_id', $this->related['ids']));
        }

        $query->scopes(['orderedIn' => [null]]);

        /** @var EloquentCollection<int, Recipe> $recipes */
        $recipes = $query->get();

        // No limit in SQL: whether a recipe is written in a language is a question of its slug,
        // and the limit counts what is shown.
        $recipes = $recipes->filter(static fn (Recipe $recipe): bool => $recipe->hasUrlIn($locale));

        if ($this->only !== null) {
            $order = array_flip($this->only);
            $recipes = $recipes->sortBy(static fn (Recipe $recipe): int => $order[(int) $recipe->getKey()] ?? PHP_INT_MAX);
        }

        if ($this->limit !== null) {
            $recipes = $recipes->take($this->limit);
        }

        return $recipes->values();
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
            'categories' => $this->categories,
            'nutrients' => $this->nutrients,
            'related' => $this->related,
            'only' => $this->only,
            'except' => $this->except,
            'limit' => $this->limit,
            'locale' => $this->locale,
        ];

        /** @var array{categories: list<int|string>|null, nutrients: list<int>|null, related: array{type: string, ids: list<int>}|null, only: list<int>|null, except: list<int>, limit: int|null, locale: string|null} $steps */
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
            $found = RecipeCategory::query()
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
     * @param  int|string|Recipe|iterable<int|string|Recipe>  $records
     * @return list<int>
     */
    private static function ids(int|string|Recipe|iterable $records): array
    {
        $ids = [];

        foreach (is_iterable($records) ? $records : [$records] as $record) {
            if ($record instanceof Recipe) {
                $ids[] = (int) $record->getKey();
            } elseif (is_int($record) || (is_string($record) && ctype_digit($record))) {
                $ids[] = (int) $record;
            }
        }

        return array_values(array_unique($ids));
    }
}
