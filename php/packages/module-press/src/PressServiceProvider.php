<?php

declare(strict_types=1);

namespace WebxUi\Press;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Press\Handlers\OutletHandler;
use WebxUi\Press\Links\OutletLinkSource;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Panel\OutletScreen;
use WebxUi\Press\Panel\PressModule;
use WebxUi\Press\Support\Kinds;
use WebxUi\Routing\Formatters\Prefixed;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;

/**
 * Outlets with addresses, their articles, the screen they are edited on and the blocks that show
 * them. What is shared is somebody else's: the addresses are `routing`'s, the sitemap and the
 * `<head>` `module-seo`'s — which also brings the SEO tab of the form as a patch — and the blocks
 * are offered to `module-blocks`, not registered with it.
 *
 * No index route (decision 12): `/{prefix}` is a page of `module-pages` with the catalogue block.
 */
class PressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-press.php', 'webx-press');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-press');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-press');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $pages = (bool) $this->config()->get('webx-press.pages', true);

        if ($pages) {
            // Before anything is registered under it: a prefix nobody gave is a site whose
            // outlets would stand among its pages, and that is refused rather than half built.
            $this->registerAddresses(self::prefix($this->config()));
        }

        $this->app->make(ScreenRegistry::class)->register(Outlet::SCREEN, OutletScreen::build(__DIR__.'/../resources/screens/outlet-form.json', $pages));
        $this->app->make(BlockOffers::class)->offer(PressModule::ID, __DIR__.'/../resources/blocks', self::withKinds(...));
        $this->app->make(ModuleRegistry::class)->register($this->app->make(PressModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-press.php' => config_path('webx-press.php'),
        ], 'webx-press-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-press'),
        ], 'webx-press-lang');

        // The page and its parts are the least markup that works, and meant to be rewritten one
        // part at a time: a site publishes them all and deletes what it keeps as it is.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-press'),
        ], 'webx-press-views');
    }

    /**
     * The prefix, normalised — and never empty (decision 11): outlets at the root of the site
     * would argue with the tree of pages over every address.
     *
     * @throws InvalidArgumentException
     */
    public static function prefix(Config $config): string
    {
        $prefix = UrlNormaliser::key((string) $config->get('webx-press.prefix', 'press'));

        if ($prefix === '') {
            throw new InvalidArgumentException((string) __('webx-press::errors.prefix'));
        }

        return $prefix;
    }

    /**
     * The kinds of the site put into an offered block's choice of kinds, when it is installed
     * (§4.8): a field named `kinds` lists them as options. A kind added to the config afterwards is
     * added to the installed type in the panel — the offer is taken once and never again.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function withKinds(array $document): array
    {
        $schema = is_array($document['schema'] ?? null) ? $document['schema'] : [];

        foreach ($schema as $i => $field) {
            if (is_array($field) && ($field['id'] ?? null) === 'kinds') {
                $schema[$i]['props'] = [
                    ...(is_array($field['props'] ?? null) ? $field['props'] : []),
                    'options' => array_map(
                        static fn (string $key): array => ['value' => $key, 'label' => Kinds::label($key, 'en')],
                        Kinds::all(),
                    ),
                ];
            }
        }

        $document['schema'] = $schema;

        return $document;
    }

    /**
     * One route type, flat under the prefix, `Fail` on a clash — an error under the slug, not a
     * quiet `-2` — and the outlets as something a menu can point at.
     */
    private function registerAddresses(string $prefix): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: Outlet::TYPE,
            model: Outlet::class,
            formatter: new Prefixed($prefix, Slug::class),
            handler: OutletHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));

        $this->app->make(LinkSources::class)->register($this->app->make(OutletLinkSource::class));
    }

    private function config(): Config
    {
        return $this->app->make('config');
    }
}
