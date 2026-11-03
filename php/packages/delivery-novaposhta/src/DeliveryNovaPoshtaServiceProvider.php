<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Journal;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\Recording\RecordCommand;
use WebxUi\Delivery\NovaPoshta\Tracking\Tracking;
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

        $this->app->singleton(AccountKey::class, static fn (): AccountKey => new AccountKey);
        $this->app->singleton(Pace::class, function (): Pace {
            return new Pace((int) $this->app->make('config')->get('webx-delivery-novaposhta.sync.requests_per_minute', 60));
        });

        $this->app->singleton(StreetsPass::class, function (): StreetsPass {
            $config = $this->app->make('config');
            // A read-only key is somebody else's account: its streets by hand, and a sample (§6.4).
            $readOnly = (bool) $config->get('webx-delivery-novaposhta.read_only', false);
            $cron = $config->get('webx-delivery-novaposhta.sync.streets_cron');
            $sample = $config->get('webx-delivery-novaposhta.sync.streets_sample') ?? ($readOnly ? StreetsPass::READ_ONLY_SAMPLE : null);

            return new StreetsPass(
                cron: is_string($cron) && trim($cron) !== '' ? trim($cron) : ($readOnly ? 'manual' : NovaPoshtaCarrier::WEEKLY),
                requests: max(1, (int) $config->get('webx-delivery-novaposhta.sync.max_requests_per_pull', 20)),
                seconds: (float) $config->get('webx-delivery-novaposhta.sync.pull_seconds', 20),
                sample: is_numeric($sample) && (int) $sample > 0 ? (int) $sample : null,
            );
        });

        $this->app->singleton(Tracking::class, function (): Tracking {
            $config = $this->app->make('config');
            // A read-only key is somebody else's account: its parcels are its buyers' data (§9).
            $enabled = $config->get('webx-delivery-novaposhta.tracking') ?? ! (bool) $config->get('webx-delivery-novaposhta.read_only', false);

            return new Tracking(
                client: $this->app->make(Client::class),
                key: $this->app->make(AccountKey::class),
                enabled: filter_var($enabled, FILTER_VALIDATE_BOOL),
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
            $this->commands([RecordCommand::class]);

            $this->publishes([
                __DIR__.'/../config/webx-delivery-novaposhta.php' => config_path('webx-delivery-novaposhta.php'),
            ], 'webx-delivery-novaposhta-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/webx-delivery-novaposhta'),
            ], 'webx-delivery-novaposhta-lang');
        }
    }
}
