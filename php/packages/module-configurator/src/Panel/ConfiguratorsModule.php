<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Configurator\Demo\ConfiguratorsDemo;
use WebxUi\Configurator\Mcp\ConfiguratorResources;
use WebxUi\Configurator\Mcp\ConfiguratorTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The section «Configurators» (§13): where a configurator is built, priced and published.
 *
 * The panel's usual pair (decision 12): `configurators.view` opens the list and the editor and
 * may ask for a price or a check; `configurators.manage` writes, publishing included, as for pages.
 *
 * To an agent it is the same section by other doors (§14): the `configurators_*` tools behind
 * `configurators:read` and `configurators:write`, and two resources to read first — what exists,
 * and what a schema is.
 */
final class ConfiguratorsModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel — and the name `webx:setup` and `webx:demo --module` know it by. */
    public const ID = 'configurators';

    public function __construct(
        private readonly ConfiguratorTools $tools,
        private readonly ConfiguratorResources $resources,
        private readonly ConfiguratorsDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-configurator::module.title');
    }

    public function icon(): string
    {
        return 'sliders';
    }

    public function order(): int
    {
        return 560;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['configurators.view', 'configurators.manage'];
    }

    /**
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
