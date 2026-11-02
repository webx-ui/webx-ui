<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Support\ServiceProvider;
use WebxUi\Cache\CacheGroups;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryMethodLocality;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Geography\LocalityReferences;
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
        $this->app->singleton(Carriers::class);
        $this->app->singleton(MethodTypes::class);
        $this->app->singleton(Methods::class);
        $this->app->singleton(Delivery::class);
        $this->app->singleton(Points::class);
        $this->app->singleton(PointKinds::class);
        $this->app->singleton(PopularLocalities::class);

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

        // A locality a point or a carrier's binding stands on is switched off, not deleted
        // (decision 42). The popular towns and the courier's list follow a deletion by their keys.
        $this->callAfterResolving(LocalityReferences::class, static function (LocalityReferences $references): void {
            $references->table('delivery_points', 'locality_id', 'delivery points');
            $references->table('delivery_carrier_localities', 'locality_id', 'carrier bindings');
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-delivery'),
        ], 'webx-delivery-lang');
    }
}
