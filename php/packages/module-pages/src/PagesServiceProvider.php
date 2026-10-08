<?php

declare(strict_types=1);

namespace WebxUi\Pages;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use stdClass;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Pages\Audit\PageContentSource;
use WebxUi\Pages\Links\PageLinkSource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\PageForm;
use WebxUi\Pages\Panel\PagesModule;
use WebxUi\Routing\Formatters\TreePath;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

/**
 * Almost everything a page does belongs to another package, so this provider is mostly
 * registrations: the kind of entity that has addresses, the kind that is made of blocks, and
 * the section of the panel that edits them.
 */
class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('pages');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-pages.php', 'webx-pages');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-pages');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-pages');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerRouteType();
        $this->registerBlockEntity();
        $this->registerScreens();
        $this->registerLinkSource();
        $this->registerAuditSource();
        $this->registerEditedRecord();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(PagesModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-pages.php' => config_path('webx-pages.php'),
        ], 'webx-pages-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-pages'),
        ], 'webx-pages-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-pages'),
        ], 'webx-pages-views');
    }

    /**
     * The address of a page repeats the tree an editor already sees, so moving a page under
     * another one is the same gesture as changing its address.
     *
     * `Fail` rather than `Suffix`: a page address is chosen deliberately, and one that quietly
     * became `about-2` is a mistake found months later in a search result. A site that wants
     * another scheme says so in `webx-routing.types.page.formatter` and runs
     * `webx:routes:rebuild --type=page`.
     */
    private function registerRouteType(): void
    {
        $this->app->make(RouteTypes::class)->register(new RouteType(
            type: 'page',
            model: Page::class,
            formatter: TreePath::class,
            handler: PageHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));
    }

    /**
     * The editor is a described screen, so a project — or the SEO module (§12) — can add a tab
     * to it with a patch instead of a fork.
     */
    private function registerScreens(): void
    {
        $this->app->make(ScreenRegistry::class)->register(
            PageForm::SCREEN,
            __DIR__.'/../resources/screens/form.json',
        );
    }

    /**
     * A page is the ordinary thing to link to, so the picker in every link field offers them —
     * the menu is only the first of those fields (§3 of the menu spec).
     */
    /**
     * The pages' blocks and drafts, searched by the site audit for addresses of a development
     * stand — only when `webx-ui/module-audit` is installed, which this package merely suggests.
     */
    private function registerAuditSource(): void
    {
        if (class_exists(AuditContentSources::class)) {
            $this->app->make(AuditContentSources::class)->register(new PageContentSource);
        }
    }

    /**
     * The editor's heartbeat asks after a page by this name: whether it moved under the editor,
     * who moved it, who else has it open.
     */
    private function registerEditedRecord(): void
    {
        $this->app->make(EditedRecords::class)->register(
            'pages',
            ['pages.view', 'pages.manage'],
            function (string $id): ?array {
                // A page in the bin too: the editor open on it hears that it went there.
                $page = ctype_digit($id) ? Page::withTrashed()->find((int) $id) : null;

                return $page instanceof Page
                    ? ['revision' => $this->app->make(PageForm::class)->revision($page), 'model' => $page]
                    : null;
            },
            'pages.manage',
            // Where the page sits and the addresses that follow from it: a move changes neither the
            // content nor the revision, and an editor open on the page still has to catch up.
            static function (Model $page): array {
                $paths = [];

                if ($page instanceof Page) {
                    foreach ($page->loadMissing('routes')->routes as $route) {
                        if ($route->kind === Route::CANONICAL) {
                            $paths[$route->locale] = '/'.$route->path;
                        }
                    }
                }

                ksort($paths);

                return [
                    'parent_id' => $page->getAttribute('parent_id'),
                    'position' => $page->getAttribute('lft'),
                    'paths' => $paths === [] ? new stdClass : $paths,
                ];
            },
        );
    }

    private function registerLinkSource(): void
    {
        $this->app->make(LinkSources::class)->register($this->app->make(PageLinkSource::class));
    }

    /**
     * Pages are an entity made of blocks, which is what `webx:blocks:bundles --warm` needs to
     * be told: the config file of `module-blocks` cannot know them, so the module that has them
     * adds itself.
     */
    private function registerBlockEntity(): void
    {
        /** @var Config $config */
        $config = $this->app->make('config');

        /** @var list<string> $entities */
        $entities = (array) $config->get('webx-blocks.entities', []);

        if (! in_array(Page::class, $entities, true)) {
            $config->set('webx-blocks.entities', [...$entities, Page::class]);
        }
    }
}
