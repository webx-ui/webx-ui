<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Collections;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Recipes\Rendering\RecipeQuery;

/**
 * The recipes a `wx-collection` field shows: `{ "props": { "source": "recipes" } }` — the block
 * "Recipes", as a showcase or as the whole catalogue (§5.8).
 *
 * A thin layer over {@see RecipeQuery}, so an element here is the same card `recipes()` gives a
 * template. Related to services, so a block on a service's page can show "the recipes of this
 * service". No markup of its own: `Recipe` belongs to the recipe's page, and a list of recipes
 * marked up on every page it stands on would be the same recipe claimed by a dozen addresses.
 */
final class RecipesSource implements CollectionSource
{
    public const KEY = 'recipes';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-recipes::module.recipes');
    }

    public function categories(): string
    {
        return 'recipes/categories';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['service'];
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): string
    {
        return 'recipes.view';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array
    {
        $query = (new RecipeQuery)
            ->in($selection->categories)
            ->take($selection->limit)
            ->locale($locale);

        $related = $selection->related();

        if ($related !== null) {
            $query = $query->relatedTo($related['type'], $related['ids']);
        }

        return $query->get();
    }
}
