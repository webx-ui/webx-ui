<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Panel\OrdersModule;

/**
 * Orders and, in later stages, the checkout (spec §1): the tables of an order and its two sets of
 * statuses, the service every door goes through, the dictionary and the section of the panel.
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
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-orders');

        $this->app->make(ModuleRegistry::class)->register(new OrdersModule);
        $this->registerHistory();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-orders.php' => config_path('webx-orders.php'),
        ], 'webx-orders-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-orders'),
        ], 'webx-orders-lang');
    }

    /**
     * The panel's journal of an order: who changed the contacts, the recipient, the comment or
     * the note (§8). Lines, money and milestones are never edited, so they are not here; read
     * behind any right of the section.
     */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (['customer_first_name', 'customer_last_name', 'customer_phone', 'customer_email', 'recipient', 'comment', 'manager_note'] as $field) {
            $fields[$field] = 'webx-orders::module.fields.'.$field;
        }

        $this->app->make(HistoryTypes::class)->register(
            'orders.order',
            Order::class,
            fields: $fields,
            permission: ['orders.view', 'orders.manage', 'orders.refund', 'orders.statuses'],
            module: 'orders',
            label: 'webx-orders::module.order',
        );
    }
}
