<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Fixtures;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Visitors\Events\VisitorDeleting;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Account\AccountRoutes;
use WebxUi\Users\Account\AccountSection;
use WebxUi\Users\Account\DeletionWarnings;

/**
 * A module of a shop the way spec §11.2 and §14.1 have it add itself to the account: a section of
 * the menu with a badge, its pages through `AccountRoutes`, a warning before an account goes, a
 * listener that unlinks its rows — and not a line of the module of visitors changed for it.
 */
final class OrdersModule extends ServiceProvider
{
    /**
     * What the listener of `VisitorDeleting` saw: the id, and whether the row was still there.
     *
     * @var array{int, bool}|null
     */
    public static ?array $deleting = null;

    public static bool $refuse = false;

    public function boot(): void
    {
        if (! class_exists(AccountMenu::class)) {
            return;
        }

        $this->app->make(AccountMenu::class)->register(new AccountSection(
            id: 'orders', label: 'My orders', route: 'test.orders.account.index',
            icon: 'package', position: 30, active: 'test.orders.account.*',
            badge: static fn (Visitor $visitor): string => (string) DB::table('test_orders')->where('user_id', $visitor->id)->count(),
        ));

        AccountRoutes::group(static function (): void {
            Route::get('orders', static fn (): string => Blade::render('<x-webx-users::account title="My orders"><p>The orders of the visitor</p></x-webx-users::account>'))
                ->name('test.orders.account.index');
            Route::get('orders/{order}', static fn (string $order): string => Blade::render('<x-webx-users::account title="Order"><p>Order {{ $order }}</p></x-webx-users::account>', ['order' => $order]))
                ->name('test.orders.account.show');
        });

        // A page for a confirmed address only — a wallet, say.
        AccountRoutes::group(static function (): void {
            Route::get('balance', static fn (): string => 'The balance')->name('test.orders.account.balance');
        }, verified: true);

        $this->app->make(DeletionWarnings::class)->register(static function (Visitor $visitor): ?string {
            $active = DB::table('test_orders')->where('user_id', $visitor->id)->orderBy('id')->pluck('id')->all();

            return $active === [] ? null : 'Orders №'.implode(', №', $active).' will be delivered, but you will not see them here.';
        });

        Event::listen(VisitorDeleting::class, static function (VisitorDeleting $event): void {
            if (self::$refuse) {
                throw new \RuntimeException('The orders refuse.');
            }

            self::$deleting = [$event->visitorId, DB::table('users')->where('id', $event->visitorId)->exists()];
            DB::table('test_orders')->where('user_id', $event->visitorId)->update(['user_id' => null]);
        });
    }
}
