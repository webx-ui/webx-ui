<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use WebxUi\Recipes\Models\RecipeCategory;

/**
 * "Rich in" — iron, fibre: a list the editor keeps growing (decision 8), shown as chips on a
 * recipe and as the filter of the catalogue. The shared category screens, without an address.
 *
 * No permission of its own: whoever looks after the categories looks after this.
 */
final class NutrientsModule extends RecipesGroup
{
    public function id(): string
    {
        return 'recipe-nutrients';
    }

    public function title(): string
    {
        return (string) __('webx-recipes::module.nutrients');
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function order(): int
    {
        return 520;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [RecipeCategory::MANAGE];
    }
}
