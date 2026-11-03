<?php

declare(strict_types=1);

namespace WebxUi\Payments\Mcp;

use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Payments\Demo\PaymentsDemo;
use WebxUi\Payments\Panel\PaymentsHome;

/**
 * The first entry of «Payments» with the whole section for an agent (§16): the tools are named
 * `payments_*`, behind `payments:read` / `payments:write` and the section's `payments.view` /
 * `payments.manage`, the bodies of the journal behind `payments.exchange`, the actions on an order
 * behind `orders.manage`; and `payments://catalog` to read first.
 *
 * Registered in place of {@see PaymentsHome} only where `webx-ui/mcp` is installed: payments does
 * not require it, and nothing loads this class otherwise.
 */
final class PaymentsModule extends PaymentsHome implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(
        private readonly PaymentsTools $tools,
        private readonly PaymentsResources $resources,
        PaymentsDemo $demo,
    ) {
        parent::__construct($demo);
    }

    /** @return list<Tool> */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }

    /** @return list<McpResource> */
    public function mcpResources(): array
    {
        return $this->resources->all();
    }
}
