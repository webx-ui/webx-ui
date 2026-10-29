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
}
