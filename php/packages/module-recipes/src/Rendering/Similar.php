<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Localization\Locales;
use WebxUi\Recipes\Models\Recipe;

/**
 * The recipes under a recipe (§5.6).
 *
 * Chosen by hand — the role `related` — they are what is shown, visible ones in the order chosen,
 * and never topped up: an editor who chose two meant two. Nothing chosen, they are picked among
 * the visible recipes by what they share with this one: a category two points, a service two, a
 * nutrient one; nothing shared is not similar; a tie goes to the order of the list.
 *
 * The points are counted in SQL, in one query over the candidates — not by loading every recipe
 * and comparing in PHP, which is what the old site did.
 */
final class Similar
{
    /** Points per shared thing. */
    private const CATEGORY = 2;

    private const SERVICE = 2;

    private const NUTRIENT = 1;

    public function __construct(private readonly Locales $locales) {}

    /**
     * @return Collection<int, Recipe>
     */
    public function to(Recipe $recipe, int $limit, ?string $locale = null): Collection
    {
        $locale ??= $this->locales->current();

        if ($limit < 1) {
            return new Collection;
        }

        $chosen = $recipe->related('related');

        if ($chosen->isNotEmpty()) {
            /** @var Collection<int, Recipe> $shown */
            $shown = $recipe->related('related', true, $locale)
                ->filter(static fn (Model $other): bool => $other instanceof Recipe && $other->hasUrlIn($locale))
                ->take($limit)
                ->values();

            return $shown;
        }

        return $this->picked($recipe, $limit, $locale);
    }

    /**
     * @return Collection<int, Recipe>
     */
    private function picked(Recipe $recipe, int $limit, string $locale): Collection
    {
        $terms = [];

        foreach (['categories' => [self::CATEGORY, $recipe->shownCategories()->modelKeys()], 'nutrients' => [self::NUTRIENT, $recipe->shownNutrients()->modelKeys()]] as $relation => [$weight, $ids]) {
            if ($ids === []) {
                continue;
            }

            $links = $recipe->categoryLinks($relation);

            $terms[] = [$weight, $links->newPivotStatement()
                ->selectRaw('count(*)')
                ->whereColumn($links->getQualifiedForeignPivotKeyName(), $recipe->getQualifiedKeyName())
                ->whereIn($links->getQualifiedRelatedPivotKeyName(), $ids)];
        }

        $services = $recipe->related('services')->modelKeys();

        if ($services !== []) {
            $table = Relations::TABLE;

            $terms[] = [self::SERVICE, Relations::rows($recipe)
                ->selectRaw('count(*)')
                ->whereColumn($table.'.owner_id', $recipe->getQualifiedKeyName())
                ->where($table.'.owner_type', $recipe->relationKey())
                ->where($table.'.role', 'services')
                ->where($table.'.target_type', 'service')
                ->whereIn($table.'.target_id', $services)];
        }

        if ($terms === []) {
            return new Collection;
        }

        [$score, $bindings] = $this->score($terms);

        $query = $recipe->newQuery()
            ->visible()
            ->whereKeyNot($recipe->getKey())
            ->select($recipe->qualifyColumn('*'))
            ->selectRaw("({$score}) as similarity", $bindings)
            ->whereRaw("({$score}) > 0", $bindings)
            ->orderByDesc('similarity')
            ->orderBy($recipe->qualifyColumn('position'))
            ->orderBy($recipe->getQualifiedKeyName())
            ->with(Cards::RELATIONS);

        /** @var Collection<int, Recipe> $found */
        $found = $query->get();

        return $found
            ->filter(static fn (Recipe $other): bool => $other->hasUrlIn($locale))
            ->take($limit)
            ->values();
    }

    /**
     * @param  list<array{0: int, 1: QueryBuilder}>  $terms
     * @return array{0: string, 1: list<mixed>}
     */
    private function score(array $terms): array
    {
        $parts = [];
        $bindings = [];

        foreach ($terms as [$weight, $query]) {
            $parts[] = $weight.' * ('.$query->toSql().')';
            $bindings = [...$bindings, ...$query->getBindings()];
        }

        return [implode(' + ', $parts), $bindings];
    }
}
