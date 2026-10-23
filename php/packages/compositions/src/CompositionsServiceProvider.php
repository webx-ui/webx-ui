<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Compositions\Console\ClearCommand;
use WebxUi\Compositions\Console\CompileCommand;
use WebxUi\Compositions\Console\ShowCommand;
use WebxUi\Compositions\Doctor\CompositionsCheck;
use WebxUi\Compositions\View\Components\Place;

/**
 * The engine of compositions (COMPOSITIONS §2.1): the registry modules load their directories
 * into, the compiler, and the service a dispatcher asks which compiled view to include.
 */
class CompositionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-compositions.php', 'webx-compositions');

        $this->app->singleton(Layers::class, fn (Application $app) => new Layers($app['config'], $app->basePath()));

        $this->app->bind(Compiler::class, fn (Application $app) => new Compiler(
            $app->make(Layers::class),
            new Reader,
            new Probe($app['view'], $app['blade.compiler']),
            $app->make(Filesystem::class),
            rtrim((string) $app['config']->get('webx-compositions.compiled', $app->storagePath('framework/compositions')), '/'),
            $app->basePath(),
            $app->configPath('webx-compositions.php'),
        ));

        // What the show command, the theme's check and the doctor read: the sources, not the compiled set.
        $this->app->bind(Inspector::class, fn (Application $app) => new Inspector($app->make(Layers::class), new Probe($app['view'], $app['blade.compiler'])));

        $this->app->scoped(Compositions::class, function (Application $app): Compositions {
            $check = $app['config']->get('webx-compositions.check');

            return new Compositions(
                $app->make(Compiler::class),
                $app['view'],
                $app['log'],
                is_bool($check) ? $check : (bool) $app['config']->get('view.check_cache_timestamps', true),
            );
        });
    }

    public function boot(): void
    {
        Blade::component('webx-composition::place', Place::class);

        // The compiled set against its sources, in the frame's `webx:doctor` — where the frame is
        // installed; the engine does not need it.
        if (class_exists(DoctorChecks::class)) {
            $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
                $checks->register(CompositionsCheck::class);
            });
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webx-compositions.php' => config_path('webx-compositions.php'),
            ], 'webx-compositions-config');

            $this->commands([CompileCommand::class, ClearCommand::class, ShowCommand::class]);
        }
    }
}
