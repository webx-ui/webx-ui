<?php

declare(strict_types=1);

namespace WebxUi\Orders\Site;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Account\AccountSection;
use WebxUi\Users\Account\DeletionWarnings;

/**
 * The orders of a visitor, where `webx-ui/module-users` is installed (spec §13.2, §13.3): the
 * section «My orders» of the account with its badge, the warning before an account goes, and the
 * tab «Orders» with the count in the visitor's card of the panel, and the account of an order on
 * its card. Without the module none of it
 * is registered — not a route, not a node — and the signed page of an order works the same.
 */
final class VisitorOrders
{
    /** The card of a visitor in the panel. */
    public const string USER_SCREEN = 'users.form';

    /** Whether the visitors module is there: its provider binds the menu of the account. */
    public static function installed(): bool
    {
        return Container::getInstance()->bound(AccountMenu::class);
    }

    public static function register(Application $app): void
    {
        $app->afterResolving(AccountMenu::class, static function (AccountMenu $menu): void {
            if ($menu->get('orders') !== null) {
                return;
            }

            $menu->register(new AccountSection(
                id: 'orders',
                label: 'webx-orders::site.account.menu',
                route: OrderLinks::ACCOUNT,
                icon: 'package',
                position: 30,
                active: 'webx.orders.account.*',
                badge: static function (Visitor $visitor): ?string {
                    $count = OrderPage::active($visitor->id)->count();

                    return $count > 0 ? (string) $count : null;
                },
            ));
        });

        $app->afterResolving(DeletionWarnings::class, static function (DeletionWarnings $warnings): void {
            $warnings->register(static function (Visitor $visitor): ?string {
                $numbers = OrderPage::active($visitor->id)->orderBy('id')->limit(5)->pluck('number')->map(static fn (mixed $number): string => '№'.$number)->all();

                return $numbers === [] ? null : __('webx-orders::site.account.deletion', ['numbers' => implode(', ', $numbers)]);
            });
        });

        $screens = $app->make(ScreenRegistry::class);
        $screens->extend(self::USER_SCREEN, self::patch());
        // The account of the order, first on its tab «Buyer» (§13.3).
        $screens->extend(OrdersModule::SCREEN, [[
            'op' => 'add',
            'target' => 'customer',
            'position' => 'first',
            'node' => [
                'id' => 'visitor-card',
                'type' => 'wx-card',
                'label' => 'trans::webx-orders::panel.visitor-title',
                'children' => [['id' => 'visitor', 'type' => 'wx-orders-visitor']],
            ],
        ]]);
    }

    /**
     * The tab «Orders» after the main one, and the count at the end of the side card — both drawn
     * by the module's own nodes, which read the visitor's id from the card; neither for somebody
     * without `orders.view`.
     *
     * @return list<array<string, mixed>>
     */
    public static function patch(): array
    {
        return [
            [
                'op' => 'add',
                'target' => 'tabs',
                'position' => 'after:main',
                'node' => [
                    'id' => 'orders-tab',
                    'type' => 'wx-tab',
                    'label' => 'trans::webx-orders::panel.visitor-orders',
                    'can' => OrdersModule::VIEW,
                    'children' => [[
                        'id' => 'orders-card',
                        'type' => 'wx-card',
                        'children' => [[
                            'id' => 'orders-list',
                            'type' => 'wx-orders-list',
                            'props' => ['filter' => 'user'],
                        ]],
                    ]],
                ],
            ],
            [
                'op' => 'add',
                'target' => 'summary',
                'node' => [
                    'id' => 'orders-count',
                    'type' => 'wx-orders-count',
                    'can' => OrdersModule::VIEW,
                ],
            ],
        ];
    }
}
