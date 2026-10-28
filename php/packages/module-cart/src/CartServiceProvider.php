<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Cart\Console\PruneCommand;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Doctor\CurrencyCheck;
use WebxUi\Cart\Mcp\CartModule;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\HintSources;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;

/**
 * The cart: its registries, the currency source, `cart()` and the prune.
 *
 * The registries are bound in `register()` so that the owner of a record type, or a satellite
 * with a source of the chain, can add itself from its own `boot()` whatever order the providers
 * boot in — through `callAfterResolving`, guarded by `class_exists`, the cart being optional.
 */
class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-cart.php', 'webx-cart');

        $this->app->singleton(Purchasables::class);
        $this->app->singleton(LineSources::class);
        $this->app->singleton(AdjustmentSources::class);
        $this->app->singleton(TenderSources::class);
        $this->app->singleton(BenefitSources::class);
        $this->app->singleton(HintSources::class);
        // If: a currencies module that binds its own wins whatever order the providers run in.
        $this->app->singletonIf(CurrencySource::class, ConfigCurrency::class);
        $this->app->singleton(Money::class);
        $this->app->singleton(CartHandle::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-cart');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-cart');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Writes per IP a minute: a script gone round in circles, not a visitor, hits it.
        RateLimiter::for('webx-cart', function (Request $request): Limit {
            $perMinute = $this->app->make('config')->get('webx-cart.throttle');

            return Limit::perMinute(is_numeric($perMinute) ? max(1, (int) $perMinute) : 60)->by((string) $request->ip());
        });

        // What one request remembered of the visitor's cart is not the next one's.
        Event::listen(RequestHandled::class, function (): void {
            if ($this->app->resolved(CartHandle::class)) {
                $this->app->make(CartHandle::class)->forget();
            }
        });

        if (class_exists(DoctorChecks::class)) {
            $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
                $checks->register(CurrencyCheck::class);
            });
        }

        // The agent's tools, where the panel and the MCP server are: the cart requires neither.
        if (class_exists(ModuleRegistry::class) && interface_exists(ProvidesMcpTools::class)) {
            $this->callAfterResolving(ModuleRegistry::class, function (ModuleRegistry $modules): void {
                if (! $modules->has(CartModule::ID)) {
                    $modules->register($this->app->make(CartModule::class));
                }
            });
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if ($this->app->make('config')->get('webx-cart.prune_after_days') !== null) {
                $schedule->command('webx:cart:prune')->daily()->name('webx-cart:prune');
            }
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([PruneCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-cart.php' => config_path('webx-cart.php'),
        ], 'webx-cart-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-cart'),
        ], 'webx-cart-lang');
    }
}
