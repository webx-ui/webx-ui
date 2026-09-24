<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Services\Demo\ServicesDemo;
use WebxUi\Services\Mcp\ServicesResources;
use WebxUi\Services\Mcp\ServiceTools;

/**
 * Where services are written, ordered and published (§4.6, §4.9).
 *
 * The panel's usual pair: `services.view` opens the list, `services.manage` writes — including the
 * order, which is a decision about the catalogue as much as a title is.
 *
 * To an agent it is the same section by other doors (§4.8): eight tools behind `services:read` and
 * `services:write`, and `services://catalog` to read first. The whole catalogue is seeded from
 * here — the categories with the services — because the one service in two categories is the
 * point of the demo, and it takes both halves to show it.
 */
final class ServicesModule extends ServicesGroup implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(
        private readonly ServiceTools $tools,
        private readonly ServicesResources $resources,
        private readonly ServicesDemo $demo,
    ) {}

    public function id(): string
    {
        return 'services';
    }

    public function title(): string
    {
        return (string) __('webx-services::module.services');
    }

    public function icon(): string
    {
        return 'list';
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
        return ['services.view', 'services.manage'];
    }

    /**
     * The block types a service is written in, and the library its cover comes out of.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return ['blocks', 'media'];
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
