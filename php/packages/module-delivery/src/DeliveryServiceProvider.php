<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Cache\CacheGroups;
use WebxUi\Delivery\Console\SyncCommand;
use WebxUi\Delivery\Doctor\SyncCheck;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryMethodLocality;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\StoredLeadingCarriers;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Geography\LocalityReferences;
use WebxUi\Geography\Matching\LeadingCarriers;
use WebxUi\Geography\Models\GeoCountry;

/**
 * Delivery: the registries of carriers and method types with the shop's own two built in, the
 * methods of a locality, the popular towns and the words of `webx-delivery`.
 *
 * The registries are bound in `register()`, so that a satellite adds its carrier and types from
 * its own `boot()` through `callAfterResolving`, whatever order the providers boot in.
 */
class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-delivery.php', 'webx-delivery');

        $this->app->singleton(Carriers::class);
        $this->app->singleton(MethodTypes::class);
        $this->app->singleton(Methods::class);
        $this->app->singleton(Delivery::class);
        $this->app->singleton(Points::class);
        $this->app->singleton(Streets::class);
        $this->app->singleton(PointKinds::class);
        $this->app->singleton(PopularLocalities::class);
        $this->app->singleton(Directories::class);
        $this->app->singleton(Synchronizer::class);

        // The first carrier to sync a country leads it in every process, not only in the one that
        // claimed it (§4.4): the geography library keeps the claim in memory only.
        $this->app->singleton(LeadingCarriers::class, StoredLeadingCarriers::class);

        $this->app->afterResolving(MethodTypes::class, static function (MethodTypes $types): void {
            $types->register(StorePickup::class);
            $types->register(StoreCourier::class);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-delivery');

        // Methods, carriers, the localities of the shop's courier and the countries are read by
        // every request of the delivery step (§17).
        $groups = $this->app->make(CacheGroups::class);
        $groups->declare(Methods::GROUP, 'webx-delivery::delivery.cache');
        $groups->bumpOn(Methods::GROUP, DeliveryMethod::class, DeliveryCarrier::class, DeliveryMethodLocality::class, GeoCountry::class);
        // The points a method shows in a locality: the shop's own by their events, the carriers'
        // by the sync, which writes by `upsert` and bumps the group itself (§17).
        $groups->declare(Synchronizer::POINTS_GROUP, 'webx-delivery::delivery.cache_points');
        $groups->bumpOn(Synchronizer::POINTS_GROUP, DeliveryPoint::class);

        // A locality a point or a carrier's binding stands on is switched off, not deleted
        // (decision 42). The popular towns and the courier's list follow a deletion by their keys.
        $this->callAfterResolving(LocalityReferences::class, static function (LocalityReferences $references): void {
            $references->table('delivery_points', 'locality_id', 'delivery points');
            $references->table('delivery_carrier_localities', 'locality_id', 'carrier bindings');
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $this->schedule($schedule);
        });

        // The directories in `webx:doctor`, where the panel's frame is installed.
        if (class_exists(DoctorChecks::class)) {
            $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
                $checks->register(SyncCheck::class);
            });
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([SyncCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-delivery.php' => config_path('webx-delivery.php'),
        ], 'webx-delivery-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-delivery'),
        ], 'webx-delivery-lang');
    }

    /**
     * Each carrier's directories by their cron (§7.7): directories on one cron go in one event,
     * in order, so that the points of a night follow its localities and only a successful pass
     * of them. The run itself goes to the queue, which tries a failed page again.
     */
    private function schedule(Schedule $schedule): void
    {
        $directories = $this->app->make(Directories::class);

        foreach (array_keys($directories->carriers()) as $carrier) {
            $byCron = [];

            foreach ($directories->schedules($carrier) as $name => $directory) {
                $cron = $directories->cron($carrier, $directory);

                if ($cron !== null) {
                    $byCron[$cron][] = $name;
                }
            }

            foreach ($byCron as $cron => $names) {
                $schedule->command('webx:delivery:sync', [$carrier, '--directory' => $names, '--trigger' => 'schedule', '--queue'])
                    ->cron($cron)
                    ->name("webx-delivery:sync:{$carrier}:".implode('+', $names))
                    ->withoutOverlapping()
                    ->onOneServer();
            }
        }
    }
}
