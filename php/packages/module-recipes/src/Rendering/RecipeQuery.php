<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
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
 * What a reader may see is not a step: published, out of the bin and written in the language
 * being read. The order is the one order recipes have (decision 4), even inside one category,
 * except with `only()`, where the order given is the point. The shape of a card is
 * {@see Cards}, the same one a `wx-collection` field hands over. The steps themselves and their
 * rules are {@see RecordQuery}'s.
 *
 * @extends RecordQuery<Recipe>
 */
final class RecipeQuery extends RecordQuery
{
    /**
     * Only what is filed under these categories — any of them: an id, a slug, a category, or a
     * list. Nothing is no filter at all; a slug nobody has matches no recipe.
     *
     * @param  int|string|RecipeCategory|iterable<int|string|RecipeCategory>|null  $categories
     */
    public function in(int|string|RecipeCategory|iterable|null $categories): self
    {
        return $this->withCategories($categories);
    }

    /**
     * Only what is rich in any of these. Nothing is no filter.
     *
     * @param  int|iterable<int|string>|null  $nutrients
     */
    public function nutrients(int|iterable|null $nutrients): self
    {
        $ids = [];

        foreach (is_iterable($nutrients) ? $nutrients : [$nutrients] as $nutrient) {
            if (is_int($nutrient) || (is_string($nutrient) && ctype_digit($nutrient))) {
                $ids[] = (int) $nutrient;
            }
        }

        $ids = array_values(array_unique($ids));

        return $this->withStep('nutrients', $ids === [] ? null : $ids);
    }

    /**
     * Only what is related to these records of another module — in any role. An empty list
     * matches nothing: "the recipes of no service" is not every recipe.
     *
     * @param  int|object|iterable<int|string|object>  $records
     */
    public function relatedTo(string $type, int|object|iterable $records): self
    {
        return $this->withRelated($type, $records);
    }

    protected function newQuery(string $locale): Builder
    {
        return Recipe::query()->visible()->with(Cards::RELATIONS);
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        return $record->hasUrlIn($locale);
    }

    protected function categoryModel(): string
    {
        return RecipeCategory::class;
    }

    protected function narrow(Builder $query, string $locale): void
    {
        /** @var list<int>|null $nutrients */
        $nutrients = $this->step('nutrients');

        if ($nutrients === null) {
            return;
        }

        $model = $query->getModel();
        $links = $model->categoryLinks('nutrients');

        $query->whereIn($model->qualifyColumn($model->getKeyName()), $links->newPivotStatement()
            ->select($links->getForeignPivotKeyName())
            ->whereIn($links->getRelatedPivotKeyName(), $nutrients));
    }

    /** One order for every list, a category's included (decision 4). */
    protected function order(Builder $query, ?int $category): void
    {
        $query->scopes(['orderedIn' => [null]]);
    }

    protected function cards(array $records, string $locale): array
    {
        return Container::getInstance()->make(Cards::class)->recipes($records, $locale);
    }
}
