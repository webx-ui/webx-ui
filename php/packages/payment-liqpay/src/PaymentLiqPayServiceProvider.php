<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay;

use Illuminate\Support\ServiceProvider;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\MethodTypes;

/**
 * The satellite of LiqPay (spec §4): its gateway and its one type join the base's registries when
 * they are first resolved, the migration adds the method switched off.
 */
class PaymentLiqPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-payment-liqpay.php', 'webx-payment-liqpay');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-payment-liqpay');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->callAfterResolving(Gateways::class, static function (Gateways $gateways): void {
            $gateways->register(LiqPayGateway::class);
        });

        $this->callAfterResolving(MethodTypes::class, static function (MethodTypes $types): void {
            $types->register(LiqPayCheckoutType::class);
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webx-payment-liqpay.php' => config_path('webx-payment-liqpay.php'),
            ], 'webx-payment-liqpay-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/webx-payment-liqpay'),
            ], 'webx-payment-liqpay-lang');
        }
    }
}
