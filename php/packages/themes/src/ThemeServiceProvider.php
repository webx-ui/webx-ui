<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

/**
 * Slots the theme chain into Blade's lookup (spec §7.1):
 *
 *     site       resources/views, resources/views/vendor/<ns>
 *     local      theme/views, theme/views/vendor/<ns>
 *     default    vendor/webx-ui/theme-default/views, …/views/vendor/<ns>
 *     modules    their own resources/views
 *
 * With `webx-themes.theme` empty it does nothing at all, so a site without a theme behaves
 * exactly as it did before the package was installed.
 */
class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-themes.php', 'webx-themes');

        $this->app->singleton(ThemeLocator::class, fn (Application $app) => new ThemeLocator($app->basePath()));

        $this->app->singleton(ThemeChain::class, function (Application $app): ThemeChain {
            $reference = $this->reference();

            return $reference === null ? new ThemeChain : ThemeChain::resolve($reference, $app->make(ThemeLocator::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webx-themes.php' => config_path('webx-themes.php'),
            ], 'webx-themes-config');
        }

        if ($this->reference() === null) {
            return;
        }

        // Booted, not boot: a module registers its namespace in its own boot(), and the order
        // providers boot in is the order Composer happened to install them. Waiting for the
        // whole application is the one way to see every module's namespace, whatever the order.
        $this->app->booted(function (): void {
            $this->arrangeViews($this->app->make(ThemeChain::class));
        });
    }

    private function arrangeViews(ThemeChain $chain): void
    {
        /** @var Factory $views */
        $views = $this->app->make('view');
        $finder = $views->getFinder();

        foreach ($chain->viewPaths() as $path) {
            $views->addLocation($path);
        }

        if ($finder instanceof FileViewFinder) {
            foreach ($finder->getHints() as $namespace => $hints) {
                $this->arrangeNamespace($views, $chain, $namespace, $hints);
            }

            $finder->flush();
        }
    }

    /**
     * `prependNamespace()` would put the theme above the site's own `resources/views/vendor/<ns>`
     * — the one place a site expects to always win — so the hints are rebuilt whole instead.
     *
     * @param  list<string>  $hints
     */
    private function arrangeNamespace(Factory $views, ThemeChain $chain, string $namespace, array $hints): void
    {
        $layers = $chain->namespaceViewPaths($namespace);

        if ($layers === []) {
            return;
        }

        // The same strings `loadViewsFrom()` builds, so they can be told apart from the module's.
        $site = [];

        foreach ((array) $this->app['config']->get('view.paths', []) as $path) {
            if (is_string($path) && in_array($path.'/vendor/'.$namespace, $hints, true)) {
                $site[] = $path.'/vendor/'.$namespace;
            }
        }

        $modules = array_values(array_diff($hints, $site, $layers));

        $views->replaceNamespace($namespace, [...$site, ...$layers, ...$modules]);
    }

    private function reference(): ?string
    {
        $theme = $this->app['config']->get('webx-themes.theme');

        return is_string($theme) && trim($theme) !== '' ? trim($theme) : null;
    }
}
