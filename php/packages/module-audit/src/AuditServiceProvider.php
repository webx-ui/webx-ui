<?php

declare(strict_types=1);

namespace WebxUi\Audit;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Throwable;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\Config;
use WebxUi\Audit\Checks\Host;
use WebxUi\Audit\Checks\Hosts;
use WebxUi\Audit\Checks\Hreflang;
use WebxUi\Audit\Checks\Indexing;
use WebxUi\Audit\Checks\JsonLd;
use WebxUi\Audit\Checks\Page;
use WebxUi\Audit\Checks\Redirects;
use WebxUi\Audit\Checks\Resources;
use WebxUi\Audit\Console\RunCommand;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Crawl\PageReaders;
use WebxUi\Audit\Fixes\AuditFixes;
use WebxUi\Audit\Fixes\ReplaceHost;
use WebxUi\Audit\Panel\AuditModule;
use WebxUi\Audit\Probes\CertificateReader;
use WebxUi\Audit\Runs\Nightly;

/**
 * The audit: its registries, its own checks, the section, the settings tab and the command.
 *
 * The registries are bound in `register()` so that a content module can add its source or its
 * checks from its own `boot()` whatever order the providers boot in (§7).
 */
class AuditServiceProvider extends ServiceProvider
{
    /** The checks that ship with the module, in the order the catalogue lists them. */
    private const CHECKS = [
        Config\Debug::class,
        Config\Environment::class,
        Config\AppUrl::class,
        Config\Queue::class,
        Config\QueueWorker::class,
        Config\Mail::class,
        Config\Schedule::class,
        Config\StorageLink::class,
        Config\SiteGate::class,
        Host\Mirror::class,
        Host\Https::class,
        Host\Tls::class,
        Host\Hsts::class,
        Host\IndexFiles::class,
        Host\Slashes::class,
        Host\TrailingSlash::class,
        Host\LetterCase::class,
        Host\Soft404::class,
        Host\NotFoundPage::class,
        Host\Compression::class,
        Host\SecurityHeaders::class,
        Host\ServerLeak::class,
        Host\StaticCache::class,
        Host\DirectoryListing::class,
        Host\ByIp::class,
        Host\Http2::class,
        Indexing\RobotsMissing::class,
        Indexing\RobotsDisallowAll::class,
        Indexing\RobotsNoSitemap::class,
        Indexing\RobotsBlocksAssets::class,
        Indexing\RobotsSyntax::class,
        Indexing\SitemapMissing::class,
        Indexing\SitemapLimits::class,
        Indexing\SitemapLastmod::class,
        Indexing\SitemapDuplicate::class,
        Hosts\DevContent::class,
        Page\HomeNoindex::class,
        Page\Noindex::class,
        Page\IndexingNofollow::class,
        Indexing\SitemapBadUrl::class,
        Indexing\SitemapMissingPage::class,
        Redirects\Chain::class,
        Redirects\Loop::class,
        Redirects\ToError::class,
        Redirects\Temporary::class,
        Page\MetaRefresh::class,
        Page\LinksToRedirect::class,
        Page\LinksToNonCanonical::class,
        Page\LinksToNoindex::class,
        Page\LinksUtm::class,
        Page\TitleMissing::class,
        Page\TitleDuplicate::class,
        Page\TitleLength::class,
        Page\TitleMultiple::class,
        Page\TitleWidth::class,
        Page\MetaMultiple::class,
        Page\DescriptionMissing::class,
        Page\DescriptionDuplicate::class,
        Page\DescriptionLength::class,
        Page\H1Missing::class,
        Page\H1Multiple::class,
        Page\H1EqualsTitle::class,
        Page\H1Duplicate::class,
        Page\H1Length::class,
        Page\HeadingsSkipped::class,
        Page\H1NotFirst::class,
        Page\HeadingsEmpty::class,
        Page\CanonicalMissing::class,
        Page\CanonicalRelative::class,
        Page\CanonicalMultiple::class,
        Page\CanonicalBroken::class,
        Page\CanonicalOther::class,
        Page\CanonicalChain::class,
        Page\CanonicalLoop::class,
        Page\CanonicalForeign::class,
        Page\CanonicalFragment::class,
        Page\CanonicalPagination::class,
        Hreflang\NotReciprocal::class,
        Hreflang\NoXDefault::class,
        Hreflang\Broken::class,
        Hreflang\SelfMissing::class,
        Hreflang\DuplicateLang::class,
        Hreflang\NotIndexable::class,
        Hreflang\LangMismatch::class,
        Page\HtmlLang::class,
        Page\Viewport::class,
        Page\Favicon::class,
        Page\HtmlDoctype::class,
        Page\HtmlCharset::class,
        Page\HtmlObsolete::class,
        Page\OpenGraph::class,
        Resources\OgImage::class,
        Resources\OgImageSmall::class,
        JsonLd\Invalid::class,
        JsonLd\Required::class,
        JsonLd\Recommended::class,
        Page\Thin::class,
        Page\TextRatio::class,
        Page\ContentDuplicate::class,
        Page\Placeholder::class,
        Page\SoftNotFound::class,
        Page\UrlLength::class,
        Page\UrlFormat::class,
        Page\UrlParams::class,
        Page\Ttfb::class,
        Page\HtmlSize::class,
        Page\BrokenLinks::class,
        Resources\ExternalBroken::class,
        Page\EmptyLinks::class,
        Page\Unfollowable::class,
        Page\VagueAnchors::class,
        Page\NofollowInternal::class,
        Page\MixedContent::class,
        Page\InsecureForms::class,
        Page\ImagesAlt::class,
        Resources\ImagesBroken::class,
        Resources\ImagesHeavy::class,
        Resources\ImagesFormat::class,
        Resources\ImagesRedirect::class,
        Resources\ImagesAltLong::class,
        Resources\AssetsBroken::class,
        Resources\AssetsHeavy::class,
        Page\ImagesDimensions::class,
        Page\ButtonName::class,
        Page\FormLabel::class,
        Page\IframeTitle::class,
        Page\Depth::class,
        Page\Orphan::class,
        Page\DeadEnd::class,
        Page\IncomingNoindexOnly::class,
        Page\IncomingNofollowOnly::class,
        Page\IncomingSingle::class,
        Page\InternalMany::class,
        Hosts\DevPage::class,
        Hosts\WrongMirror::class,
        Hosts\Similar::class,
        Hosts\NewDomain::class,
        Hosts\AbsoluteOwn::class,
        Hosts\BlankOpener::class,
        Resources\ExternalRedirect::class,
        Page\ExternalMany::class,
    ];

    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            // Every result belongs to the stand it was found on.
            $tables->stand('audit_runs', 'audit_pages', 'audit_issues', 'audit_ignores', 'audit_links', 'audit_resources', 'audit_content_urls');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-audit.php', 'webx-audit');

        $this->app->singleton(AuditChecks::class);
        $this->app->singleton(AuditContentSources::class);
        $this->app->singleton(AuditFixes::class);
        $this->app->singleton(PageReaders::class);
        $this->app->singleton(CertificateReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-audit');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $checks = $this->app->make(AuditChecks::class);

        foreach (self::CHECKS as $check) {
            $checks->register($this->app->make($check));
        }

        $this->app->make(AuditFixes::class)->register($this->app->make(ReplaceHost::class));

        $this->app->make(ModuleRegistry::class)->register($this->app->make(AuditModule::class));

        // The section's own settings: where the site is, its stands, the limits, the thresholds,
        // the nightly run and the history — a screen, so a project can patch a field in.
        $this->app->make(ScreenRegistry::class)->register(AuditSettings::SCREEN, __DIR__.'/../resources/screens/audit-settings.json');

        $this->registerHeartbeat();
        $this->registerNightly();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([RunCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-audit.php' => config_path('webx-audit.php'),
        ], 'webx-audit-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-audit'),
        ], 'webx-audit-lang');
    }

    /**
     * A beat on the schedule every minute, so `config.schedule` can tell a scheduler that runs
     * from one that was never put in cron. Put there by the package, like the backup: a site has
     * nothing to remember.
     */
    private function registerHeartbeat(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->call(function (): void {
                $this->app->make(Cache::class)->forever(Config\Schedule::HEARTBEAT, Carbon::now()->getTimestamp());
            })->everyMinute()->name('webx-audit:heartbeat');
        });
    }

    /**
     * The nightly run (§8): off until an administrator switches it on, then at the hour and of
     * the scope the settings say. The settings are read when the scheduler builds its list —
     * every minute, in a fresh process — so a change is on the schedule a minute later.
     */
    private function registerNightly(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            try {
                $nightly = $this->app->make(AuditSettings::class)->schedule();
            } catch (Throwable) {
                // No settings table yet — a fresh install before its migrations.
                return;
            }

            if ($nightly === null) {
                return;
            }

            $schedule->call(function () use ($nightly): void {
                $this->app->make(Nightly::class)->run($nightly['scope']);
            })->dailyAt(sprintf('%02d:00', $nightly['hour']))->name('webx-audit:nightly');
        });
    }
}
