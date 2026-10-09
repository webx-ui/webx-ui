<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use WebxUi\Themes\Console\MakeCommand;
use WebxUi\Themes\Console\SyncCommand;
use WebxUi\Themes\Contracts\Appearance;

/**
 * Slots the theme chain into Blade's lookup (spec §7.1):
 *
 *     site       resources/views, resources/views/vendor/<ns>
 *     local      theme/views, theme/views/vendor/<ns>
 *     default    vendor/webx-ui/theme-default/views, …/views/vendor/<ns>
 *     modules    their own resources/views
 *
 * and gives the page its tokens: `@webxTheme`, `theme_token()` (§7.3).
 *
 * With `webx-themes.theme` empty it does nothing at all, so a site without a theme behaves
 * exactly as it did before the package was installed: `@webxTheme` is still there for a layout
 * that has it, and prints nothing.
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

        // If: the panel's «Appearance» tab binds its own, and may register before or after this.
        $this->app->singletonIf(Appearance::class, fn (Application $app) => new ConfigAppearance($app->make('config')));

        // Scoped: the owner's choice may change between two requests of one long-lived worker.
        $this->app->scoped(Tokens::class, fn (Application $app) => new Tokens($app->make(ThemeChain::class), $app->make(Appearance::class)));

        $this->app->singleton(BottomLayers::class);

        $this->app->singleton(ThemeAssets::class, fn (Application $app) => new ThemeAssets($app->publicPath()));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webx-themes.php' => config_path('webx-themes.php'),
            ], 'webx-themes-config');

            $this->commands([MakeCommand::class, SyncCommand::class]);
        }

        // `var_export` rather than a literal class name: the compiled string has to carry the
        // namespace separators.
        Blade::directive('webxTheme', static fn (): string => sprintf('<?php echo app(%s)->render(); ?>', var_export(ThemeHead::class, true)));

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
