<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * «Orders» in the panel (spec §11): the list, the card, the statuses and the settings of the
 * section, behind four permissions of its own — reading, working an order, money given back, and
 * the statuses with the settings. The checkout's tab adds a fifth with its own stage (§31.1).
 */
final class OrdersModule extends AbstractModule
{
    /** The id in the panel — and the name `webx:setup` knows the module by. */
    public const string ID = 'orders';

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
        return ['orders.view', 'orders.manage', 'orders.refund', 'orders.statuses'];
    }
}
