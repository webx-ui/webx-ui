<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Mcp;

use WebxUi\Delivery\Demo\DeliveryDemo;
use WebxUi\Delivery\Panel\DeliveryHome;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The first entry of «Delivery» — the methods — with the whole section for an agent (§15): the
 * tools are named `delivery_*`, behind `delivery:read` / `delivery:write` and the section's
 * `delivery.view` / `delivery.manage`, the waybills behind `delivery.ship`; and
 * `delivery://catalog` to read first.
 *
 * Registered in place of {@see DeliveryHome} only where `webx-ui/mcp` is installed: delivery does
 * not require it, and nothing loads this class otherwise.
 */
final class DeliveryModule extends DeliveryHome implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(
        private readonly DeliveryTools $tools,
        private readonly GeographyTools $geography,
        private readonly CarrierTools $carriers,
        private readonly DeliveryResources $resources,
        DeliveryDemo $demo,
    ) {
        parent::__construct($demo);
    }

    /** @return list<Tool> */
    public function mcpTools(): array
    {
        return [...$this->tools->all(), ...$this->geography->all(), ...$this->carriers->all()];
    }

    /** @return list<McpResource> */
    public function mcpResources(): array
    {
        return $this->resources->all();
    }
}
