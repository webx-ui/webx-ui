<?php

declare(strict_types=1);

namespace WebxUi\Seo;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route as RouteRow;
use WebxUi\Seo\Console\SitemapCommand;
use WebxUi\Seo\Http\Middleware\RedirectRequests;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\DefaultsSource;
use WebxUi\Seo\Panel\SeoModule;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Panel\UrlRuleSource;
use WebxUi\Seo\Rendering\EntitySource;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\SeoSources;
use WebxUi\Seo\Screens\SeoFieldType;
use WebxUi\Seo\Sitemap\Sitemap;
use WebxUi\Seo\Sitemap\SitemapRoutes;
use WebxUi\Settings\Events\SettingsSaved;
use WebxUi\Settings\Settings;

/**
 * Two halves of one package, wired here.
 *
 * `Rendering\` is what a public page uses: the resolver, the sources it asks, and the block it
 * prints. `Panel\` is what fills those sources — the tables, the matcher, the section. The seam
 * between them is the `SeoSource` contract and nothing else, so a content module that one day
 * wants the rendering without the section can be given it by moving one directory.
 */
class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-seo.php', 'webx-seo');

        $this->app->singleton(SeoSources::class);
        $this->app->singleton(SeoRules::class);
        $this->app->singleton(UrlMatcher::class);
        $this->app->singleton(Seo::class);
        $this->app->singleton(Sitemap::class);
        $this->app->singleton(SitemapRoutes::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-seo');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-seo');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerSources();
        $this->registerRendering();
        $this->registerRedirects();
        $this->registerFieldType();
        $this->registerSitemap();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(SeoModule::class));

        // The SEO tab on the settings screen. A patch may name a screen nobody has registered
        // yet — the registry applies it when the tree is first built — so the order in which
        // this provider and the settings one boot does not matter.
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->extend(Settings::SCREEN, __DIR__.'/../resources/screens/settings.json');

        // The card on the page editor, for the same reason and by the same mechanism: a
        // content module describes its screen, and whoever has something to add to it adds it
        // from their own provider rather than being named in somebody else's description. A
        // patch on a screen that is not registered — a panel without `module-pages` — is
        // simply never applied.
        $screens->extend('pages.form', __DIR__.'/../resources/screens/pages.form.json');
        $screens->extend('blog.article-form', __DIR__.'/../resources/screens/blog.article-form.json');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([SitemapCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-seo.php' => config_path('webx-seo.php'),
        ], 'webx-seo-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-seo'),
        ], 'webx-seo-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-seo'),
        ], 'webx-seo-views');
    }

    /**
     * The sources that ship with the module, in the order they will be asked: the rules an
     * editor wrote for an address, then what the entity on the page says about itself, then
     * what the site says when nobody said anything.
     */
    private function registerSources(): void
    {
        $sources = $this->app->make(SeoSources::class);

        $sources->register($this->app->make(UrlRuleSource::class));
        $sources->register($this->app->make(EntitySource::class));
        $sources->register($this->app->make(DefaultsSource::class));
    }

    /**
     * `wx-seo` as a field any screen can carry.
     *
     * Registered here rather than by whoever uses it, so that a content module gets the card by
     * patching one node into its screen and putting `HasSeo` on its model — and gets the same
     * card, checked the same way, as every other one.
     */
    private function registerFieldType(): void
    {
        $this->app->make(FieldTypes::class)->register('wx-seo', $this->app->make(SeoFieldType::class));
    }

    /**
     * The built sitemap is thrown away by whatever could change it (§17.3): a row of the address
     * registry, a card, a rule, an entity that can be visible, a setting of this module.
     *
     * One wildcard listener rather than an observer per model, because most of those models are
     * not this module's — a visible entity is whatever a content module declared, and asking
     * each module to remember to tell the sitemap is how the one that forgets gets a stale map.
     * The check is an `instanceof`; the price of the wildcard is that on every save.
     */
    private function registerSitemap(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app->make('events');

        $events->listen(
            ['eloquent.saved: *', 'eloquent.deleted: *', 'eloquent.restored: *', 'eloquent.moved: *'],
            function (string $event, array $payload): void {
                $model = $payload[0] ?? null;

                if ($model instanceof RouteRow || $model instanceof SeoMeta || $model instanceof SeoUrl || $model instanceof Visible) {
                    $this->app->make(Sitemap::class)->refresh();
                }
            },
        );

        $events->listen(SettingsSaved::class, function (SettingsSaved $saved): void {
            foreach ($saved->keys as $key) {
                if (str_starts_with($key, 'seo.')) {
                    $this->app->make(Sitemap::class)->refresh();

                    return;
                }
            }
        });
    }

    /**
     * `@webxSeo` and `<x-webx-seo::head />` are the same call written two ways: a template that
     * has an entity to name wants the tag, one that does not wants the directive.
     *
     * A namespace rather than an alias: the prefix is the one the views already answer to, so
     * the tag names the package to install, and the next component of this module is a file in
     * the same directory rather than another global name to keep clear of.
     */
    private function registerRendering(): void
    {
        Blade::componentNamespace('WebxUi\\Seo\\View\\Components', 'webx-seo');

        Blade::directive('webxSeo', static function (string $expression): string {
            $subject = trim($expression) === '' ? 'null' : $expression;

            // `var_export` rather than a literal class name: the compiled string has to carry
            // the namespace separators, and every other way of writing that is one escape away
            // from a class that does not exist.
            return sprintf('<?php echo app(%s)->head(%s); ?>', var_export(Seo::class, true), $subject);
        });
    }

    /**
     * Redirects run as global middleware, and that is not a shortcut — it is the only place
     * they work.
     *
     * The addresses worth redirecting are the ones the site no longer has a route for, and a
     * request for an address with no route never reaches any middleware group: the router
     * throws before the group is entered. Put in `web`, a redirect table fires only on pages
     * that still exist, which is the one case nobody needs it for. Global middleware runs
     * before routing, which is where this belongs.
     *
     * The alias stays for an application that would rather place it itself.
     *
     * On `booted` rather than here, so that resolving the HTTP kernel — which copies its own
     * middleware groups over the router's as it is constructed — cannot undo something another
     * provider did while booting.
     */
    private function registerRedirects(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('webx.redirects', RedirectRequests::class);

        $this->app->booted(function (): void {
            $kernel = $this->app->make(HttpKernel::class);

            if ($kernel instanceof Kernel) {
                $kernel->pushMiddleware(RedirectRequests::class);
            }
        });
    }
}
