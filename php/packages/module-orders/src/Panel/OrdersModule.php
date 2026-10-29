<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Orders\Demo\OrdersDemo;
use WebxUi\Orders\Mcp\OrdersResources;
use WebxUi\Orders\Mcp\OrdersTools;
use WebxUi\Orders\Mcp\StatusTools;

/**
 * «Orders» in the panel (spec §11): the list, the card, the statuses and the settings of the
 * section, behind four permissions of its own — reading, working an order, money given back, and
 * the statuses with the settings. The checkout's tab adds a fifth with its own stage (§31.1).
 *
 * The same for an agent (§12): the `orders_*` tools and `orders://catalog`. The demo (§15) is twelve
 * orders made through the service, the visitor's among them when visitors are installed.
 */
final class OrdersModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel — and the name `webx:setup` knows the module by. */
    public const string ID = 'orders';

    /** The list, the card and the history — read. */
    public const string VIEW = 'orders.view';

    /** The status, the marks, the cancellation, the contacts and the note, the blocks. */
    public const string MANAGE = 'orders.manage';

    /** Money given back — apart from the rest, because it is money. */
    public const string REFUND = 'orders.refund';

    /** The screens of the statuses and the settings of the section. */
    public const string STATUSES = 'orders.statuses';

    /** The card of an order, a described screen neighbours add their tabs to (§11.3). */
    public const string SCREEN = 'orders.show';

    /**
     * The tools are made when the server asks for them, not when the module is registered: they
     * hold the service, and a service made at boot would keep the events dispatcher of that moment.
     */
    public function __construct(private readonly Container $container) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-orders::module.orders');
    }

    public function icon(): string
    {
        return 'clipboard';
    }

    public function order(): int
    {
        return 350;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [self::VIEW, self::MANAGE, self::REFUND, self::STATUSES];
    }

    /** @return list<string> */
    public function requires(): array
    {
        return $this->container->make(OrdersDemo::class)->requires();
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->container->make(OrdersDemo::class)->seed($ledger);
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return [...$this->container->make(OrdersTools::class)->all(), ...$this->container->make(StatusTools::class)->all()];
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return $this->container->make(OrdersResources::class)->all();
    }
}
