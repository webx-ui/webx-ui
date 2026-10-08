<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\Events\StoredContentRewritten;
use WebxUi\Admin\Gate\Openings;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Fixes\AuditFixes;
use WebxUi\Blocks\Audit\PruneStrayValuesFix;
use WebxUi\Blocks\Audit\RegionContentSource;
use WebxUi\Blocks\Audit\StrayValuesCheck;
use WebxUi\Blocks\Console\BundlesCommand;
use WebxUi\Blocks\Console\ClearCommand;
use WebxUi\Blocks\Console\ExportCommand;
use WebxUi\Blocks\Console\ImportCommand;
use WebxUi\Blocks\Console\OfferedCommand;
use WebxUi\Blocks\Console\PruneCommand;
use WebxUi\Blocks\Console\RegionsCommand;
use WebxUi\Blocks\Fields\DataType;
use WebxUi\Blocks\Fields\SlotType;
use WebxUi\Blocks\Http\Middleware\EnsureEditing;
use WebxUi\Blocks\Panel\BlocksModule;
use WebxUi\Blocks\Panel\Publisher;
use WebxUi\Blocks\Panel\RegionForm;
use WebxUi\Blocks\Panel\RegionsModule;
use WebxUi\Blocks\Panel\Usage;
use WebxUi\Blocks\Preview\Preview;
use WebxUi\Blocks\Preview\PreviewToken;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Blocks\Rendering\Thumbnails;
use WebxUi\Blocks\Tags\BlockTag;

/**
 * Two halves of one package. `Rendering\` is what a public page uses: the registry of types,
 * the compiler and the renderer behind `Blocks::render()` and `@blocks`. `Panel\` is the
 * section where a type is made — the module, the controllers behind `/blocks`, what knows
 * where a type stands and what gates its publication. The seam between them is the tables.
 */
class BlocksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('blocks', 'block_versions', 'block_regions');
            // Glued from the versions that were current here; glued again on the first request.
            $tables->derived('block_bundles');
            $tables->afterRestore('webx:blocks:clear');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-blocks.php', 'webx-blocks');

        $this->app->singleton(BlockTypes::class);
        $this->app->singleton(Renderer::class);
        $this->app->singleton(Bundles::class);
        $this->app->singleton(Preview::class);
        $this->app->singleton(Usage::class);
        $this->app->singleton(Publisher::class);
        $this->app->scoped(Thumbnails::class);
        $this->app->singleton(Regions::class);

        // What modules offer as block types (§3.4 of the FAQ spec). Filled from their providers.
        $this->app->singleton(BlockOffers::class);

        // What modules hand to components and where they call them from (§3.7 of the
        // components spec). Filled from their providers, like the offers above.
        $this->app->singleton(BlockShapes::class);
        $this->app->singleton(BlockComponents::class);

        $this->app->singleton(PreviewToken::class, static function (Application $app): PreviewToken {
            $key = (string) $app->make('config')->get('app.key', '');

            if (str_starts_with($key, 'base64:')) {
                $key = (string) base64_decode(substr($key, 7), true);
            }

            return new PreviewToken($key);
        });

        $this->app->singleton(TemplateCompiler::class, static function (Application $app): TemplateCompiler {
            $config = $app->make('config');
            $directory = $config->get('webx-blocks.compiled');

            if (! is_string($directory) || $directory === '') {
                // Next to the application's own compiled views: that directory exists, is
                // writable, and is the one thing every deploy already knows to keep.
                $directory = rtrim((string) $config->get('view.compiled'), '/\\').'/blocks';
            }

            return new TemplateCompiler($app->make('blade.compiler'), $app->make('files'), $directory);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-blocks');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-blocks');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerMacro();
        $this->registerDirectives();
        $this->registerFieldTypes();

        // `<x-webx-block type="…">`: a type called from any template, the ones in the tables
        // included — they are compiled by the same Blade that compiles the site's views.
        Blade::component('webx-block', BlockTag::class);

        // `<x-webx-blocks::region>` is a class under this namespace; `<x-webx-blocks::standalone>`
        // stays the anonymous view it was — the compiler looks for a class first, then a view.
        Blade::componentNamespace('WebxUi\Blocks\View\Components', 'webx-blocks');

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('webx.blocks-editing', EnsureEditing::class);

        // An imported file is compared with what is stored, whitespace included: trimmed on the
        // way in, every template that ends with a newline would read as changed.
        $import = static fn (Request $request): bool => $request->isMethod('POST')
            && $request->is(trim((string) config('webx-admin.api_path'), '/').'/blocks/import');
        TrimStrings::skipWhen($import);
        ConvertEmptyStringsToNull::skipWhen($import);

        $this->app->make(ModuleRegistry::class)->register($this->app->make(BlocksModule::class));

        // The regions are a section of their own: edited by whoever edits the pages, not by
        // whoever may write Blade (§7.1 of the regions spec).
        $this->app->make(ModuleRegistry::class)->register($this->app->make(RegionsModule::class));
        $this->app->make(ScreenRegistry::class)->register(RegionForm::SCREEN, __DIR__.'/../resources/screens/regions.form.json');

        // The region editor's heartbeat asks after a region by its name. One nobody has saved
        // yet has no row and nothing to have moved under anybody: the heartbeat hears 404.
        $this->app->make(EditedRecords::class)->register('regions', 'blocks.regions', function (string $name): ?array {
            $region = $this->app->make(Regions::class)->find($name);

            return $region === null ? null : ['revision' => Content::revision($region->editingTree()), 'model' => $region];
        });

        $this->registerGateOpenings();
        $this->registerAuditSource();

        // What a response printed is what its bundle is glued from, and no more than that: in a
        // process that serves many requests the list would otherwise grow across them.
        $this->app->make(Dispatcher::class)->listen(RequestHandled::class, function (): void {
            $this->app->make(Renderer::class)->flush();
        });

        // Content rewritten around the models — the library moving pictures to new keys: the
        // cached registry, its thumbnails and the published trees of the regions all hold the
        // old values, and nothing else would tell them until their TTL.
        $this->app->make(Dispatcher::class)->listen(StoredContentRewritten::class, function (): void {
            $this->app->make(BlockTypes::class)->forget();
            $this->app->make(Thumbnails::class)->forget();

            $regions = $this->app->make(Regions::class);

            foreach (array_keys($regions->declared()) as $name) {
                $regions->forget($name);
            }

            $this->app->make(Renderer::class)->flush();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([BundlesCommand::class, ClearCommand::class, ExportCommand::class, ImportCommand::class, OfferedCommand::class, PruneCommand::class, RegionsCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-blocks.php' => config_path('webx-blocks.php'),
        ], 'webx-blocks-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-blocks'),
        ], 'webx-blocks-lang');
    }

    /**
     * Past the password over a site in testing: what the panel's preview frame loads.
     *
     * The editor signed in to the panel and never typed the site's pair, so a closed preview is
     * an empty frame. A draft under its token is let through because the token is the permission
     * — without a valid one it stays behind the password, as a stranger's guess should. The
     * block editor's stage checks the panel's session itself, and the bundles are what every
     * preview pulls in: stylesheets and scripts the published site hands out anyway.
     */
    /**
     * The regions' published trees and drafts, searched by the site audit for addresses of a
     * development stand — only when `webx-ui/module-audit` is installed.
     */
    private function registerAuditSource(): void
    {
        if (class_exists(AuditContentSources::class)) {
            $this->app->make(AuditContentSources::class)->register($this->app->make(RegionContentSource::class));
            // Values for fields a block type does not define: found, and taken out per entity.
            $this->app->make(AuditChecks::class)->register($this->app->make(StrayValuesCheck::class));
            $this->app->make(AuditFixes::class)->register($this->app->make(PruneStrayValuesFix::class));
        }
    }

    private function registerGateOpenings(): void
    {
        $config = $this->app->make('config');
        $openings = $this->app->make(Openings::class);

        $openings->allow(function (Request $request) use ($config, $openings): bool {
            $preview = trim((string) $config->get('webx-blocks.preview.path', '_preview'), '/');

            if ($openings->under($request, $preview.'/block-stage')) {
                return true;
            }

            $token = $request->query('token');

            // A region's draft on a page of the site: the token names the region, not a row.
            if ($preview !== '' && is_string($token) && preg_match('#^'.preg_quote($preview, '#').'/region/([a-z][a-z0-9-]*)$#', trim($request->path(), '/'), $region) === 1) {
                return $this->app->make(PreviewToken::class)->verify($token, Preview::REGION, $region[1]) !== null;
            }

            return $preview !== ''
                && is_string($token)
                && preg_match('#^'.preg_quote($preview, '#').'/([a-z0-9-]+)/([0-9]+)$#', trim($request->path(), '/'), $match) === 1
                && $this->app->make(PreviewToken::class)->verify($token, $match[1], $match[2]) !== null;
        });

        $openings->allow(static fn (Request $request): bool => $openings->under(
            $request,
            $config->get('webx-blocks.bundles.path', 'blocks'),
        ));
    }

    /**
     * A component's two kinds of input the panel does not type in: data handed over by code, and
     * a slot of markup. Both known to the server so that a walk over a schema stops at them.
     */
    private function registerFieldTypes(): void
    {
        $types = $this->app->make(FieldTypes::class);

        $types->register('wx-data', new DataType);
        $types->register('wx-slot', new SlotType);
    }

    /**
     * `$table->blocks()` — the tree the site prints — so a migration says what it adds rather
     * than how. The draft the preview reads is `$table->draft()` of `module-admin`: drafts are
     * the frame's mechanism, and an entity without blocks needs them just the same.
     */
    private function registerMacro(): void
    {
        if (! Blueprint::hasMacro('blocks')) {
            Blueprint::macro('blocks', function (string $column = 'blocks'): void {
                /** @var Blueprint $this */
                $this->json($column)->nullable();
            });
        }

        if (! Blueprint::hasMacro('dropBlocks')) {
            Blueprint::macro('dropBlocks', function (string $column = 'blocks'): void {
                /** @var Blueprint $this */
                $this->dropColumn($column);
            });
        }
    }

    /**
     * `@blocks('content')` inside a block's template prints the blocks held in that field, one
     * level down. It needs `$block`, which every block template has; outside one there is
     * nothing to nest into, and `Blocks::render()` is the call to make.
     */
    private function registerDirectives(): void
    {
        Blade::directive('blocks', static function (string $expression): string {
            $field = trim($expression) === '' ? "'content'" : $expression;

            // `var_export` rather than a literal class name: the compiled string has to carry
            // the namespace separators, and every other way of writing that is one escape away
            // from a class that does not exist.
            return sprintf('<?php echo app(%s)->nested($block, %s); ?>', var_export(Renderer::class, true), $field);
        });

        // `@webxBlocks` in the layout's head prints the stylesheet and the script of everything
        // the page rendered; `@webxBlocks('styles')` and `@webxBlocks('scripts')` split them,
        // `@webxBlocks('runtime')` prints the runtime alone. Evaluated where it stands, which
        // with `@extends` and with components is after the content — see `Bundles::tags()`.
        Blade::directive('webxBlocks', static function (string $expression): string {
            $what = trim($expression) === '' ? "'all'" : $expression;

            return sprintf('<?php echo app(%s)->tags(%s); ?>', var_export(Bundles::class, true), $what);
        });
    }
}
