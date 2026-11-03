<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Payments\Console\ExchangePruneCommand;
use WebxUi\Payments\Doctor\ExchangeSizeCheck;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeMasker;

/**
 * Payments: the tables of methods, the buyer's choice and attempts, and the journal of the
 * exchange with gateways (spec §5, §20) with the words of `webx-payments`.
 */
class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-payments.php', 'webx-payments');

        $this->app->singleton(ExchangeConnection::class);
        $this->app->singleton(ExchangeMasker::class);
        $this->app->singleton(ExchangeJournal::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-payments');

        // The size of the journal in `webx:doctor`, where the panel's frame is installed.
        if (class_exists(DoctorChecks::class)) {
            $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
                $checks->register(ExchangeSizeCheck::class);
            });
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ExchangePruneCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-payments.php' => config_path('webx-payments.php'),
        ], 'webx-payments-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-payments'),
        ], 'webx-payments-lang');
    }
}
