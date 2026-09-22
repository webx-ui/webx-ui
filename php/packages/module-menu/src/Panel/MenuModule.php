<?php

declare(strict_types=1);

namespace WebxUi\Menu\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Menu\Demo\MenuDemo;
use WebxUi\Menu\Mcp\MenuResources;
use WebxUi\Menu\Mcp\MenuTools;

/**
 * The section where the menus of the site are arranged.
 *
 * Among the content sections and after them: pages are 200 and the blog is 300, so a menu — which
 * is a way of pointing at those rather than a thing of its own — comes at 400, before the block
 * constructor that keeps the site running rather than saying what is on it.
 *
 * The panel's usual pair of permissions. `manage` covers the cache reset as well as writing,
 * because what the reset changes is what a visitor sees — that makes it an action rather than a
 * way of looking (§9).
 *
 * To an agent it is the same section by other doors (§11): six tools, the catalogue and the house
 * rules to read first, and no prompt — arranging a menu is one call at a time and needs no recipe.
 * The scopes are `menu:read` and `menu:write`.
 */
final class MenuModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(
        private readonly MenuTools $tools,
        private readonly MenuResources $resources,
        private readonly MenuDemo $demo,
    ) {}

    public function id(): string
    {
        return 'menu';
    }

    public function title(): string
    {
        return (string) __('webx-menu::module.title');
    }

    public function icon(): string
    {
        return 'menu';
    }

    public function order(): int
    {
        return 400;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['menu.view', 'menu.manage'];
    }

    /**
     * The pages, because a menu made of nothing points at nothing (§14).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return ['pages'];
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
