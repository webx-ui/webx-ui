<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Recipes\Demo\RecipesDemo;
use WebxUi\Recipes\Mcp\RecipesResources;
use WebxUi\Recipes\Mcp\RecipeTools;

/**
 * Where recipes are written, ordered and published (§5.9): `recipes.view` opens the list,
 * `recipes.manage` writes — the order included, which is a decision about the catalogue as much
 * as a title is.
 *
 * To an agent it is the same section by other doors (§5.11): eight tools behind `recipes:read` and
 * `recipes:write`, and `recipes://catalog` to read first. The demo seeds the categories and the
 * nutrients with the recipes, because a recipe in two categories and one rich in three things are
 * the point of it.
 */
final class RecipesModule extends RecipesGroup implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'recipes';

    public function __construct(
        private readonly RecipeTools $tools,
        private readonly RecipesResources $resources,
        private readonly RecipesDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-recipes::module.recipes');
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 500;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['recipes.view', 'recipes.manage'];
    }

    /**
     * Worked out, not written down: the library always, the services and the page of blocks only
     * where they are installed (§5.12).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->demo->requires();
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return $this->resources->all();
    }
}
