<?php

declare(strict_types=1);

namespace WebxUi\Team\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Team\Demo\TeamDemo;
use WebxUi\Team\Mcp\TeamResources;
use WebxUi\Team\Mcp\TeamTools;

/**
 * Where the people of the team are written, ordered and published (§5.6): one entry of the menu,
 * at the top level — with no categories there is nothing to group it with.
 *
 * The panel's usual pair: `team.view` opens the list, `team.manage` writes — including the order,
 * which is what a reader of a team block sees first.
 *
 * To an agent it is the same section by other doors (§5.8): six tools behind `team:read` and
 * `team:write`, and `team://catalog` to read first.
 */
final class TeamModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'team';

    public function __construct(
        private readonly TeamTools $tools,
        private readonly TeamResources $resources,
        private readonly TeamDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-team::module.team');
    }

    public function icon(): string
    {
        return 'users';
    }

    public function order(): int
    {
        return 650;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['team.view', 'team.manage'];
    }

    /**
     * The library always, and — when they are installed — the page and the services the team
     * block is put into.
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
