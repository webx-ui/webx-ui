<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Journal;
use WebxUi\Delivery\NovaPoshta\Types\BranchType;
use WebxUi\Delivery\NovaPoshta\Types\CourierType;
use WebxUi\Delivery\NovaPoshta\Types\LockerType;

/**
 * The satellite of Nova Poshta (§4.1): its carrier and three types join the base's registries
 * when they are first resolved, the client reads its config, the migration adds the three
 * methods switched off (decision 2).
 */
class DeliveryNovaPoshtaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-delivery-novaposhta.php', 'webx-delivery-novaposhta');

        $this->app->singleton(Journal::class, function (): Journal {
            $path = $this->app->make('config')->get('webx-delivery-novaposhta.journal');

            return new Journal(is_string($path) && $path !== '' ? $path : null);
        });

        $this->app->singleton(Client::class, function (): Client {
            $config = $this->app->make('config');

            return new Client(
                http: $this->app->make(Factory::class),
                journal: $this->app->make(Journal::class),
                url: (string) $config->get('webx-delivery-novaposhta.url'),
                timeout: (int) $config->get('webx-delivery-novaposhta.timeout', 10),
                readOnly: (bool) $config->get('webx-delivery-novaposhta.read_only', false),
            );
        });

        $this->app->singleton(NovaPoshtaCarrier::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-delivery-novaposhta');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->callAfterResolving(Carriers::class, static function (Carriers $carriers): void {
            $carriers->register(NovaPoshtaCarrier::class);
        });

        $this->callAfterResolving(MethodTypes::class, static function (MethodTypes $types): void {
            $types->register(BranchType::class);
            $types->register(LockerType::class);
            $types->register(CourierType::class);
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webx-delivery-novaposhta.php' => config_path('webx-delivery-novaposhta.php'),
            ], 'webx-delivery-novaposhta-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/webx-delivery-novaposhta'),
            ], 'webx-delivery-novaposhta-lang');
        }
    }
}
