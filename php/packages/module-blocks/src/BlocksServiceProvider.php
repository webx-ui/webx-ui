<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;

/**
 * Two halves of one package. `Rendering\` is what a public page uses: the registry of types,
 * the compiler and the renderer behind `Blocks::render()` and `@blocks`. The panel half — the
 * section where a type is made — arrives next to it; the seam between them is the tables.
 */
class BlocksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-blocks.php', 'webx-blocks');

        $this->app->singleton(BlockTypes::class);
        $this->app->singleton(Renderer::class);

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

        $this->registerMacro();
        $this->registerDirective();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-blocks.php' => config_path('webx-blocks.php'),
        ], 'webx-blocks-config');
    }

    /**
     * `$table->blocks()` — the tree the site prints and the draft the preview reads — so a
     * migration says what it adds rather than how.
     */
    private function registerMacro(): void
    {
        if (! Blueprint::hasMacro('blocks')) {
            Blueprint::macro('blocks', function (string $column = 'blocks', string $draft = 'draft'): void {
                /** @var Blueprint $this */
                $this->json($column)->nullable();
                $this->json($draft)->nullable();
            });
        }

        if (! Blueprint::hasMacro('dropBlocks')) {
            Blueprint::macro('dropBlocks', function (string $column = 'blocks', string $draft = 'draft'): void {
                /** @var Blueprint $this */
                $this->dropColumn([$column, $draft]);
            });
        }
    }

    /**
     * `@blocks('content')` inside a block's template prints the blocks held in that field, one
     * level down. It needs `$block`, which every block template has; outside one there is
     * nothing to nest into, and `Blocks::render()` is the call to make.
     */
    private function registerDirective(): void
    {
        Blade::directive('blocks', static function (string $expression): string {
            $field = trim($expression) === '' ? "'content'" : $expression;

            // `var_export` rather than a literal class name: the compiled string has to carry
            // the namespace separators, and every other way of writing that is one escape away
            // from a class that does not exist.
            return sprintf('<?php echo app(%s)->nested($block, %s); ?>', var_export(Renderer::class, true), $field);
        });
    }
}
