<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;

/**
 * "Rich in" — iron, fibre: a list the editor keeps growing (decision 8), shown as chips on a
 * recipe and as the filter of the catalogue. The shared category screens, without an address.
 *
 * No permission of its own: whoever looks after the categories looks after this.
 *
 * To an agent, the tools every module's categories have: `recipe_nutrients_list`, and create,
 * update, delete and reorder. Saying what a recipe is rich in is still `recipes_update`.
 */
final class NutrientsModule extends RecipesGroup implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CategoryForm $form) {}

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

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(RecipeNutrient::class, $this->form))->all();
    }
}
