<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Visitors\Events\VisitorDeleting;
use WebxUi\Cache\CacheGroups;
use WebxUi\Compositions\Compositions;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Checkout\Checkout;
use WebxUi\Orders\Checkout\Console\PruneDraftsCommand;
use WebxUi\Orders\Doctor\OrdersNotificationsCheck;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Notifications\OrderEvents;
use WebxUi\Orders\Notifications\SendOrderNotifications;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Orders\Panel\SettingsScreen;
use WebxUi\Orders\Site\OrderPage;
use WebxUi\Orders\Site\VisitorOrders;

/**
 * Orders and the checkout (spec §1): the tables of an order and its two sets of statuses, the
 * service every door goes through, the dictionary and the section of the panel; the checkout's
 * draft and its settings, wired in only while `webx-orders.checkout.enabled` is on (§19).
 *
 * Without a neighbour the module still stands (§1.1): the cart, the widgets and the compositions
 * are required; delivery, payment, visitors and notifications are wired in only when they are
 * installed.
 */
class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-orders.php', 'webx-orders');

        $this->app->singleton(OrderBlocks::class);
        $this->app->singleton(Orders::class);
        Checkout::bind($this->app);

        // The events of an order for the channels of `webx-ui/module-notifications`, when it is
        // there (§10); without it the module stands, and `webx:doctor` says nobody is told.
        if (class_exists(NotificationEvents::class)) {
            $this->callAfterResolving(NotificationEvents::class, static function (NotificationEvents $events): void {
                foreach (OrderEvents::definitions() as $definition) {
                    $events->register($definition);
                }
            });
        }

        $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
            $checks->register(OrdersNotificationsCheck::class);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-orders');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-orders');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        Compositions::handles('orders', ['orders.order']);

        // Both sets of statuses as the buyer's pages read them (§16).
        $groups = $this->app->make(CacheGroups::class);
        $groups->declare(OrderPage::GROUP, 'webx-orders::module.statuses-cache');
        $groups->bumpOn(OrderPage::GROUP, PublicStatus::class, OrderStatus::class);

        // An account that goes leaves its orders to nobody (§13.2): the event is the panel's, so
        // it is heard whichever visitors module deletes the account.
        Event::listen(VisitorDeleting::class, function (VisitorDeleting $event): void {
            $this->app->make(Orders::class)->forgetVisitor($event->visitorId, 'users');
        });

        // «My orders», the warning before the account goes and the visitor's tab in the panel —
        // only where there are visitors (§13.2).
        if (VisitorOrders::installed()) {
            VisitorOrders::register($this->app);
            $this->loadRoutesFrom(__DIR__.'/../routes/account.php');
        }

        $this->app->make(ModuleRegistry::class)->register($this->app->make(OrdersModule::class));
        $this->registerHistory();
        SendOrderNotifications::listen($this->app->make('events'));
        // The card of an order (§11.3): neighbours add their tabs to it with a patch of `tabs`.
        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register(OrdersModule::SCREEN, __DIR__.'/../resources/screens/show.json');
        // The settings of the section (§11.4): the choice of the initial statuses is made of the
        // statuses as they are when the tree is built.
        $screens->register(SettingsScreen::NAME, __DIR__.'/../resources/screens/settings.json');
        $screens->extend(SettingsScreen::NAME, fn (): array => $this->app->make(SettingsScreen::class)->patch());

        // Off — nothing of the checkout is registered (decision 80); the drafts' table stays.
        $checkout = Checkout::enabled();

        if ($checkout) {
            Checkout::register($this->app);
            $this->loadRoutesFrom(__DIR__.'/../routes/checkout.php');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        if ($checkout) {
            $this->commands([PruneDraftsCommand::class]);
        }

        $this->publishes([
            __DIR__.'/../config/webx-orders.php' => config_path('webx-orders.php'),
        ], 'webx-orders-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-orders'),
        ], 'webx-orders-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-orders'),
        ], 'webx-orders-views');
    }

    /**
     * The panel's journal of an order: who changed the contacts, the recipient, the comment or
     * the note (§8). Lines, money and milestones are never edited, so they are not here; read
     * behind any right of the section.
     */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (['customer_first_name', 'customer_last_name', 'customer_phone', 'customer_email', 'recipient', 'comment', 'manager_note', 'user_id'] as $field) {
            $fields[$field] = 'webx-orders::module.fields.'.$field;
        }

        $this->app->make(HistoryTypes::class)->register(
            'orders.order',
            Order::class,
            fields: $fields,
            permission: [OrdersModule::VIEW, OrdersModule::MANAGE, OrdersModule::REFUND, OrdersModule::STATUSES],
            module: 'orders',
            label: 'webx-orders::module.order',
        );
    }
}
