<?php

declare(strict_types=1);

namespace WebxUi\Press\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Press\Demo\PressDemo;
use WebxUi\Press\Mcp\PressResources;
use WebxUi\Press\Mcp\PressTools;

/**
 * Where outlets and their articles are written, ordered and published (§4.9).
 *
 * One entry in the menu and no group of its own: the module has one section. The panel's usual
 * pair of permissions — `press.view` opens the list, `press.manage` writes, including the order,
 * which is what a reader of the strip of logos sees first.
 *
 * To an agent it is the same section by other doors (§4.11): ten tools behind `press:read` and
 * `press:write` — the outlets, and their articles one at a time — and `press://catalog` to read
 * first.
 */
final class PressModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'press';

    public function __construct(
        private readonly PressTools $tools,
        private readonly PressResources $resources,
        private readonly PressDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-press::module.press');
    }

    public function icon(): string
    {
        return 'newspaper';
    }

    public function order(): int
    {
        return 620;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['press.view', 'press.manage'];
    }

    /**
     * The library the logos go into, the block types, and — when installed — the page the blocks
     * go onto.
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
