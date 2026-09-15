<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Blocks\Console\BundlesCommand;
use WebxUi\Blocks\Console\ClearCommand;
use WebxUi\Blocks\Http\Middleware\EnsureEditing;
use WebxUi\Blocks\Panel\BlocksModule;
use WebxUi\Blocks\Panel\Publisher;
use WebxUi\Blocks\Panel\Usage;
use WebxUi\Blocks\Preview\Preview;
use WebxUi\Blocks\Preview\PreviewToken;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;

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
        $this->mergeConfigFrom(__DIR__.'/../config/webx-blocks.php', 'webx-blocks');

        $this->app->singleton(BlockTypes::class);
        $this->app->singleton(Renderer::class);
        $this->app->singleton(Bundles::class);
        $this->app->singleton(Preview::class);
        $this->app->singleton(Usage::class);
        $this->app->singleton(Publisher::class);

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
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerMacro();
        $this->registerDirectives();

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('webx.blocks-editing', EnsureEditing::class);

        $this->app->make(ModuleRegistry::class)->register($this->app->make(BlocksModule::class));

        // What a response printed is what its bundle is glued from, and no more than that: in a
        // process that serves many requests the list would otherwise grow across them.
        $this->app->make(Dispatcher::class)->listen(RequestHandled::class, function (): void {
            $this->app->make(Renderer::class)->flush();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([BundlesCommand::class, ClearCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-blocks.php' => config_path('webx-blocks.php'),
        ], 'webx-blocks-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-blocks'),
        ], 'webx-blocks-lang');
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
