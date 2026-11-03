<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Cache\CacheGroups;
use WebxUi\Orders\Blocks\BlockDefinition;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Checkout\PaymentsCheckoutSlot;
use WebxUi\Payments\Console\ExchangePruneCommand;
use WebxUi\Payments\Console\SelectionsPruneCommand;
use WebxUi\Payments\Doctor\ExchangeSizeCheck;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeMasker;
use WebxUi\Payments\Exchange\InProcessHosts;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\Checks;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Online\Handoffs;
use WebxUi\Payments\Online\Notifications;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Online\PayPage;
use WebxUi\Payments\Online\Returns;
use WebxUi\Payments\Online\Settlement;
use WebxUi\Payments\Online\StartRequests;
use WebxUi\Payments\Rendering\Assets;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * Payments: the registry of method types with the shop's own two built in, the methods of the
 * site offered by the amount and the delivery, the checkout's step «payment», the order's block
 * `payment` with the bank details, a manager's «Mark as paid», and the journal of the exchange
 * with gateways (spec §5–§12, §20) with the words of `webx-payments`.
 *
 * The registry is bound in `register()`, so that a satellite adds its types from its own `boot()`
 * through `callAfterResolving`, whatever order the providers boot in.
 */
class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-payments.php', 'webx-payments');

        $this->app->singleton(ExchangeConnection::class);
        $this->app->singleton(ExchangeMasker::class);
        $this->app->singleton(ExchangeJournal::class);

        $this->app->singleton(MethodTypes::class);
        $this->app->singleton(Gateways::class);
        $this->app->singleton(InProcessHosts::class);
        $this->app->singleton(MethodCredentials::class);
        $this->app->singleton(GatewayCalls::class);
        $this->app->singleton(PaymentLinks::class);
        $this->app->singleton(StartRequests::class);
        $this->app->singleton(PayPage::class);
        $this->app->singleton(Handoffs::class);
        $this->app->singleton(Settlement::class);
        $this->app->singleton(Checks::class);
        $this->app->singleton(Notifications::class);
        $this->app->singleton(Returns::class);
        $this->app->singleton(Methods::class);
        $this->app->singleton(Payments::class);
        $this->app->singleton(PaymentsCheckoutSlot::class);
        // What one request read of a cart's choice is not the next one's.
        $this->app->scoped(Selections::class);
        // The flag «the script is on this page already» belongs to the response being built.
        $this->app->scoped(Assets::class);

        $this->app->afterResolving(MethodTypes::class, static function (MethodTypes $types): void {
            $types->register(OnReceipt::class);
            $types->register(BankTransfer::class);
        });

        // «Gateway A» (§21): only with WEBX_PAYMENTS_FAKE_GATEWAY and never in production.
        FakeGatewaySite::register($this->app);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-payments');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-payments');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/pay.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/fake.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/notify.php');

        RateLimiter::for('webx-payments-write', function (Request $request): Limit {
            $perMinute = $this->app->make('config')->get('webx-payments.throttle.write');

            return Limit::perMinute(is_numeric($perMinute) ? max(1, (int) $perMinute) : 30)->by((string) $request->ip());
        });

        // The enabled methods are read by every request of the payment step (§18).
        $groups = $this->app->make(CacheGroups::class);
        $groups->declare(Methods::GROUP, 'webx-payments::module.cache');
        $groups->bumpOn(Methods::GROUP, PaymentMethod::class);

        // The checkout's slot «payment» (§10; §22.3 of the orders spec).
        $this->callAfterResolving(CheckoutSlots::class, function (CheckoutSlots $slots): void {
            if ($slots->payment() === null) {
                $slots->register($this->app->make(PaymentsCheckoutSlot::class));
            }
        });

        // The order's block `payment` (§10.4): its line is the method's title, and under it, while
        // the order awaits payment, the bank details of a transfer.
        $this->callAfterResolving(OrderBlocks::class, static function (OrderBlocks $blocks): void {
            if (! $blocks->has('payment')) {
                $blocks->register(new BlockDefinition(
                    'payment',
                    'payments',
                    PaymentSnapshot::validate(...),
                    PaymentSnapshot::summary(...),
                    details: static function (array $block, Order $order, string $locale): ?string {
                        if (! $order->awaiting_payment || $order->paid_at !== null || $order->cancelled_at !== null) {
                            return null;
                        }

                        $lines = Instructions::of($block, (string) $order->number, $locale);

                        return $lines === null ? null : Blade::render('<x-webx-payments::instructions :lines="$lines" />', ['lines' => $lines]);
                    },
                ));
            }
        });

        // The size of the journal in `webx:doctor`, where the panel's frame is installed.
        if (class_exists(DoctorChecks::class)) {
            $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
                $checks->register(ExchangeSizeCheck::class);
            });
        }

        // The choices of carts the cart's own pruning took (§5).
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('webx:payments:selections-prune')->dailyAt('03:40')->withoutOverlapping()->onOneServer();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ExchangePruneCommand::class, SelectionsPruneCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-payments.php' => config_path('webx-payments.php'),
        ], 'webx-payments-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-payments'),
        ], 'webx-payments-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-payments'),
        ], 'webx-payments-views');
    }
}
