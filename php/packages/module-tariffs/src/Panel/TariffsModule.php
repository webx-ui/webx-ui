<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Tariffs\Demo\TariffsDemo;
use WebxUi\Tariffs\Mcp\TariffsResources;
use WebxUi\Tariffs\Mcp\TariffTools;

/**
 * Where tariffs are written, ordered and published (§5.2).
 *
 * The panel's usual pair: `tariffs.view` opens the list, `tariffs.manage` writes — including the
 * order, which is what a reader of a page of prices sees first.
 *
 * To an agent it is the same section by other doors (§5.5): six tools behind `tariffs:read` and
 * `tariffs:write`, and `tariffs://catalog` to read first. The groups are their own section, with
 * the tools every module's categories have (`tariff_groups_*`).
 */
final class TariffsModule extends TariffsGroup implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'tariffs';

    public function __construct(
        private readonly TariffTools $tools,
        private readonly TariffsResources $resources,
        private readonly TariffsDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-tariffs::module.tariffs');
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function order(): int
    {
        return 680;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['tariffs.view', 'tariffs.manage'];
    }

    /**
     * When they are installed, the pages and the services the tariffs block is put into.
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
