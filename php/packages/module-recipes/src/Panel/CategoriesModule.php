<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Recipes\Models\RecipeCategory;

/**
 * The categories of the recipes: flat, ordered by hand, several per recipe, each a page of the
 * site — the panel's shared category screens with this module's words.
 *
 * Its own permission, because renaming a section of the site is a different job from writing a
 * recipe in it. The same permission writes what recipes are rich in: one job, one person.
 *
 * To an agent, the tools every module's categories have: `recipe_categories_list`, and create,
 * update, delete and reorder. Filing a recipe into a category is still `recipes_update`.
 */
final class CategoriesModule extends RecipesGroup implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CategoryForm $form) {}

    public function id(): string
    {
        return 'recipe-categories';
    }

    public function title(): string
    {
        return (string) __('webx-recipes::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 510;
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
        return (new CategoryTools(RecipeCategory::class, $this->form))->all();
    }
}
