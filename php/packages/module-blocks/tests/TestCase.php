<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Blocks\Tests\Fixtures\PageHandler;
use WebxUi\Blocks\Tests\Fixtures\RoutedPage;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\RoutingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            BlocksServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->blocks();
            $table->draft();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        // What a content module registers in its own provider: the type the preview route and
        // the resolver both hand a page to. `note` is the same without an address, for the
        // preview of a record the registry knows nothing about.
        $types = $this->app->make(RouteTypes::class);

        $types->register(new RouteType(
            type: 'page',
            model: RoutedPage::class,
            formatter: Slug::class,
            handler: PageHandler::class,
        ));

        $types->register(new RouteType(
            type: 'note',
            model: Page::class,
            formatter: Slug::class,
            handler: PageHandler::class,
        ));
    }

    protected function tearDown(): void
    {
        // One compiled file per version, and every test starts its versions at 1: a file left
        // by the previous test would be served for a template it was not compiled from.
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    /**
     * A block type with one published version, the way the panel would have made it.
     *
     * Unless the test says otherwise, the schema declares every `$variable` the template
     * mentions: the renderer only knows the fields the schema names, and a test about
     * rendering should not have to spell out a field list to get its template to run.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $content
     */
    protected function publish(string $slug, string $template, array $attributes = [], array $content = []): Block
    {
        $block = Block::query()->create(['slug' => $slug, 'title' => ucfirst($slug)] + $attributes);

        if (! isset($content['schema'])) {
            preg_match_all('/\$([a-z][a-z0-9_]*)/i', $template, $found);

            $content['schema'] = array_map(
                static fn (string $id): array => ['id' => $id, 'type' => 'wx-input'],
                array_values(array_diff(array_unique($found[1]), ['block', 'entity', '__env'])),
            );
        }

        $block->saveVersion(['template' => $template] + $content);
        $block->publish();

        return $block->refresh();
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     */
    protected function render(array $blocks, ?object $entity = null, bool $preview = false): string
    {
        return (string) $this->app->make(Renderer::class)->preview($preview)->render($blocks, $entity);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function node(string $type, array $values = [], ?string $key = null): array
    {
        static $count = 0;
        $count++;

        return ['key' => $key ?? "k{$count}", 'type' => $type, 'values' => $values];
    }
}
