<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Cache\CacheGroups;
use WebxUi\Cart\BenefitKeys;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Delivery\Checkout\DeliveryCheckoutSlot;
use WebxUi\Delivery\Checkout\StepView;
use WebxUi\Delivery\Console\SyncCommand;
use WebxUi\Delivery\Doctor\SyncCheck;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryMethodLocality;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Panel\DeliverySection;
use WebxUi\Delivery\Panel\Fields\CountryField;
use WebxUi\Delivery\Panel\Fields\LocalitiesField;
use WebxUi\Delivery\Panel\Fields\LocalityField;
use WebxUi\Delivery\Panel\Fields\RegionField;
use WebxUi\Delivery\Panel\Fields\ScheduleField;
use WebxUi\Delivery\Panel\LocalityForm;
use WebxUi\Delivery\Panel\MethodForm;
use WebxUi\Delivery\Panel\PointForm;
use WebxUi\Delivery\Rendering\Assets;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\StoredLeadingCarriers;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Delivery\View\Components\Step;
use WebxUi\Geography\LocalityKind;
use WebxUi\Geography\LocalityReferences;
use WebxUi\Geography\Matching\LeadingCarriers;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;

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
        $this->app->singleton(Quotes::class);
        // What one request read of a cart's delivery is not the next one's.
        $this->app->scoped(CartDelivery::class);
        $this->app->singleton(Points::class);
        $this->app->singleton(Countries::class);
        // They read and write the choice through CartDelivery, which is the request's.
        $this->app->scoped(Selections::class);
        $this->app->scoped(StepView::class);
        // The flag «the script is on this page already» belongs to the response being built.
        $this->app->scoped(Assets::class);
        $this->app->singleton(DeliveryCheckoutSlot::class);
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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-delivery');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // The step (§12.3): the class component, and its parts — the anonymous views under components/.
        Blade::component('webx-delivery::step', Step::class);

        // Suggestions and points per IP a minute (§12.2), and the writes of the choice.
        foreach (['webx-delivery' => 'suggest', 'webx-delivery-write' => 'write'] as $limiter => $key) {
            RateLimiter::for($limiter, function (Request $request) use ($key): Limit {
                $perMinute = $this->app->make('config')->get('webx-delivery.throttle.'.$key);

                return Limit::perMinute(is_numeric($perMinute) ? max(1, (int) $perMinute) : 60)->by((string) $request->ip());
            });
        }

        // The checkout's slot «delivery», where module-orders is installed (§22.2 of its spec).
        if (class_exists(CheckoutSlots::class)) {
            $this->callAfterResolving(CheckoutSlots::class, function (CheckoutSlots $slots): void {
                if ($slots->shipping() === null) {
                    $slots->register($this->app->make(DeliveryCheckoutSlot::class));
                }
            });
        }

        // Methods, carriers, the localities of the shop's courier and the countries are read by
        // every request of the delivery step (§17).
        $groups = $this->app->make(CacheGroups::class);
        $groups->declare(Methods::GROUP, 'webx-delivery::delivery.cache');
        $groups->bumpOn(Methods::GROUP, DeliveryMethod::class, DeliveryCarrier::class, DeliveryMethodLocality::class, GeoCountry::class);
        // The points a method shows in a locality: the shop's own by their events, the carriers'
        // by the sync, which writes by `upsert` and bumps the group itself (§17).
        $groups->declare(Synchronizer::POINTS_GROUP, 'webx-delivery::delivery.cache_points');
        $groups->bumpOn(Synchronizer::POINTS_GROUP, DeliveryPoint::class);

        // The price of delivery in the cart's chain, after the discounts (§9.3), and the benefit the
        // shop's rules may grant for it (§9.4): the rules offer the key from a list.
        $this->callAfterResolving(AdjustmentSources::class, function (AdjustmentSources $sources): void {
            $sources->register($this->app->make(DeliveryAdjustments::class), DeliveryAdjustments::PRIORITY);
        });
        $this->callAfterResolving(BenefitKeys::class, static function (BenefitKeys $keys): void {
            $keys->register(Benefits::PAID_BY_STORE, 'webx-delivery::delivery.benefits.paid_by_store');
        });

        // A locality a point or a carrier's binding stands on is switched off, not deleted
        // (decision 42). The popular towns and the courier's list follow a deletion by their keys.
        $this->callAfterResolving(LocalityReferences::class, static function (LocalityReferences $references): void {
            $references->table('delivery_points', 'locality_id', 'delivery points');
            $references->table('delivery_carrier_localities', 'locality_id', 'carrier bindings');
            $references->table('delivery_selections', 'locality_id', 'buyers\' choices');
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

        // The section of the panel (§14), where the panel's frame is installed.
        if (class_exists(ModuleRegistry::class)) {
            $this->registerPanel();
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

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-delivery'),
        ], 'webx-delivery-views');
    }

    /**
     * Six entries in the group «Delivery», their routes, and the three described forms with the
     * fields only this section has. The choices of the selects come from the database, so they are
     * laid over the screens by closures — run when a screen is asked for, not at boot.
     *
     * The menu group is added to the panel's config at boot rather than shipped as a default: a
     * site that published `webx-admin.php` has its own copy of the list.
     */
    private function registerPanel(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/panel.php');

        $config = $this->app->make('config');
        $groups = (array) $config->get('webx-admin.groups', []);

        if (! array_key_exists(DeliverySection::GROUP, $groups)) {
            $config->set('webx-admin.groups', [
                ...$groups,
                DeliverySection::GROUP => ['title' => 'webx-delivery::panel.sections.group', 'icon' => 'send', 'order' => DeliverySection::ORDER],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach (DeliverySection::all() as $section) {
            $modules->register($section);
        }

        $types = $this->app->make(FieldTypes::class);
        $types->register('wx-delivery-locality', new LocalityField);
        $types->register('wx-delivery-localities', new LocalitiesField);
        $types->register('wx-delivery-region', new RegionField);
        $types->register('wx-delivery-country', new CountryField);
        $types->register('wx-delivery-schedule', new ScheduleField);

        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register(MethodForm::SCREEN, __DIR__.'/../resources/screens/method.json');
        $screens->register(PointForm::SCREEN, __DIR__.'/../resources/screens/point.json');
        $screens->register(LocalityForm::SCREEN, __DIR__.'/../resources/screens/locality.json');

        $screens->extend(MethodForm::SCREEN, fn (): array => [
            ['op' => 'set', 'target' => 'countries', 'props' => ['options' => $this->app->make(MethodForm::class)->countryOptions()]],
            ['op' => 'set', 'target' => 'point_kind', 'props' => ['options' => $this->app->make(MethodForm::class)->pointKindOptions()]],
        ]);
        $screens->extend(PointForm::SCREEN, fn (): array => [
            ['op' => 'set', 'target' => 'kind', 'props' => ['options' => $this->app->make(PointForm::class)->kindOptions()]],
        ]);
        $screens->extend(LocalityForm::SCREEN, static fn (): array => [
            ['op' => 'set', 'target' => 'kind', 'props' => ['options' => array_map(
                static fn (string $kind): array => ['value' => $kind, 'label' => (string) __('webx-delivery::delivery.locality_kinds.'.$kind)],
                LocalityKind::values(),
            )]],
        ]);
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
