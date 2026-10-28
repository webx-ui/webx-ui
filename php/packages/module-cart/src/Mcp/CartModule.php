<?php

declare(strict_types=1);

namespace WebxUi\Cart\Mcp;

use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The carts for an agent (§10): four tools behind `cart:read` and `cart.view`, and `cart://catalog`.
 *
 * A module of its own so that the tools are named `cart_*` and the permission has a row in the
 * matrix of roles; it has no page, and the panel's menu leaves out a module the front end has no
 * routes for. Nothing here writes: a cart is the visitor's, and only the visitor changes it.
 *
 * Registered by the cart's provider only where the panel and `webx-ui/mcp` are installed — the
 * cart requires neither, and nothing loads this class otherwise.
 */
final class CartModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const string ID = 'cart';

    public function __construct(
        private readonly CartTools $tools,
        private readonly CartResources $resources,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-cart::module.title');
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['cart.view'];
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
