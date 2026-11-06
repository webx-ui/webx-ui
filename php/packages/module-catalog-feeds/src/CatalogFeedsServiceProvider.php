<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\Help\HelpRegistry;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Catalog\Mcp\SatelliteTools;
use WebxUi\CatalogFeeds\Console\FeedsCommand;
use WebxUi\CatalogFeeds\Console\FeedsPruneCommand;
use WebxUi\CatalogFeeds\Doctor\FeedsCheck;
use WebxUi\CatalogFeeds\Formats\FeedFormats;
use WebxUi\CatalogFeeds\Mcp\FeedResources;
use WebxUi\CatalogFeeds\Mcp\FeedTools;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Panel\CatalogFeedsModule;
use WebxUi\CatalogFeeds\Presets\CsvPreset;
use WebxUi\CatalogFeeds\Presets\FeedPresets;
use WebxUi\CatalogFeeds\Presets\GooglePreset;
use WebxUi\CatalogFeeds\Presets\JsonPreset;
use WebxUi\CatalogFeeds\Presets\XmlPreset;
use WebxUi\CatalogFeeds\Taxonomy\TaxonomyImport;

/**
 * Product feeds of the catalogue (`WEBX_UI_CATALOG_FEEDS.md`): a satellite that knows only the
 * core. A feed has no address in the routes' registry — it is a file behind a key, not a page —
 * so there is no `RouteType` and nothing for `SiteBar::entity()`.
 */
class CatalogFeedsServiceProvider extends ServiceProvider
{
    /** Where the feeds answer under the panel's API. */
    public const SOURCE = 'catalog/feeds';

    /** Its own, by key and address: a bare `throttle` would count a robot with the sign-in form. */
    public const LIMITER = 'webx-catalog-feeds';

    public function register(): void
    {
        // A feed's settings are content; its builds are what happened on this stand, and their
        // files stay here, so a restored feed answers 404 until it is built again.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('catalog_feeds', 'catalog_feed_exclusions', 'catalog_feed_categories', 'catalog_feed_taxonomies', 'catalog_feed_taxonomy_items');
            $tables->stand('catalog_feed_builds', 'catalog_feed_build_rejects');
        });

        $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
            $checks->register(FeedsCheck::class);
        });

        // `onOneServer`: two watchers would each see the same feed due.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(FeedsCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command(FeedsPruneCommand::class)->dailyAt('04:10')->onOneServer();
        });

        $this->app->singleton(FeedPresets::class, static fn (): FeedPresets => new FeedPresets([
            new GooglePreset, new JsonPreset, new XmlPreset, new CsvPreset,
        ]));

        $this->app->singleton(FeedFormats::class);

        $this->mergeConfigFrom(__DIR__.'/../config/webx-catalog-feeds.php', 'webx-catalog-feeds');
    }

    public function boot(): void
    {
        $this->app->make(HelpRegistry::class)->directory(__DIR__.'/../resources/help');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-catalog-feeds');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/public.php');

        $this->reservePrefix();
        $this->registerRateLimiter();
        $this->keepTemplatesAsTyped();

        $screens = $this->app->make(ScreenRegistry::class);
        $screens->register(Feed::SCREEN, __DIR__.'/../resources/screens/feed-form.json');
        $screens->rules(Feed::SCREEN, ['name' => ['required', 'string', 'max:120']]);

        $this->registerHistory();
        $this->registerUpload();
        $this->app->make(ModuleRegistry::class)->register($this->app->make(CatalogFeedsModule::class));

        // The agent's tools and resources are the catalogue's (§12): its names, scopes and
        // permissions. Built when an agent asks, not at boot.
        $mcp = $this->app->make(SatelliteTools::class);
        $mcp->tools(fn (): array => $this->app->make(FeedTools::class)->all());
        $mcp->resources(fn (): array => $this->app->make(FeedResources::class)->all());

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([FeedsCommand::class, FeedsPruneCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-catalog-feeds.php' => config_path('webx-catalog-feeds.php'),
        ], 'webx-catalog-feeds-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-catalog-feeds'),
        ], 'webx-catalog-feeds-lang');
    }

    /** The file of a marketplace's list, in pieces like any other; the reader refuses a wrong one. */
    private function registerUpload(): void
    {
        $this->app->make(UploadPurposes::class)->register(
            TaxonomyImport::UPLOAD_PURPOSE,
            permission: 'catalog.manage',
            types: ['text/plain'],
            maxBytes: fn (): int => max(1, (int) $this->app->make('config')->get('webx-catalog-feeds.taxonomy_max_bytes')),
            extensions: ['txt'],
        );
    }

    /** A route with a parameter cannot be asked about one address at a time, so a page could take it. */
    private function reservePrefix(): void
    {
        $config = $this->app->make('config');

        $config->set('webx-routing.reserved', [
            ...(array) $config->get('webx-routing.reserved', []),
            trim((string) $config->get('webx-catalog-feeds.path'), '/'),
        ]);
    }

    /**
     * A template is written to the byte: the line break that ends the head of a csv is what keeps
     * the first product off the line of the column names. A skip takes the whole request, so it is
     * for the form's own addresses only, and `FeedForm::cleaned()` does the rest of the form's fields.
     */
    private function keepTemplatesAsTyped(): void
    {
        $form = static function (Request $request): bool {
            $base = trim((string) config('webx-admin.api_path'), '/').'/'.self::SOURCE;

            return ! $request->isMethod('GET')
                && preg_match('#^'.preg_quote($base, '#').'(/preview|/sample|/\d+)?$#', $request->path()) === 1;
        };

        TrimStrings::skipWhen($form);
        ConvertEmptyStringsToNull::skipWhen($form);
    }

    /** Hashed: the key is in the path, and it stays out of the cache's keys as of the logs. */
    private function registerRateLimiter(): void
    {
        RateLimiter::for(self::LIMITER, function (Request $request): Limit {
            return Limit::perMinute((int) $this->app->make('config')->get('webx-catalog-feeds.per_minute', 60))
                ->by(hash('sha256', (string) $request->route('key')).'|'.$request->ip());
        });
    }

    /** What the journal shows for a feed: "Template", not `item`. The key is never among them (§13). */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (Feed::HISTORY as $field) {
            $fields[$field] = 'webx-catalog-feeds::feed.'.$field;
        }

        $this->app->make(HistoryTypes::class)->register(
            Feed::TYPE,
            Feed::class,
            fields: $fields,
            permission: ['catalog.view', 'catalog.manage', 'catalog.delete'],
            module: 'catalog-feeds',
            label: 'webx-catalog-feeds::module.feed',
        );
    }
}
