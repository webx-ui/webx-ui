<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\Formatters\SlugSku;
use WebxUi\Routing\Formatters\TreePath;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Routing\Tests\Fixtures\Article;
use WebxUi\Routing\Tests\Fixtures\Category;
use WebxUi\Routing\Tests\Fixtures\Page;
use WebxUi\Routing\Tests\Fixtures\PageHandler;
use WebxUi\Routing\Tests\Fixtures\Product;

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
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
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
        // Every test here writes and then reads; a cached list of languages would outlive the
        // change that a test is about.
        $app['config']->set('webx-localization.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('slug')->nullable();
            // Publication is the entity's business, never the registry's (§2, decision 9); the
            // handler is what reads this, and the resolution tests are what prove it.
            $table->boolean('published')->default(true);
            $table->nestedSet();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTypes();
    }

    /** The registrations a set of content modules would make in their own providers. */
    protected function registerTypes(): void
    {
        $types = $this->app->make(RouteTypes::class);

        $types->register(new RouteType(
            type: 'page',
            model: Page::class,
            formatter: TreePath::class,
            handler: PageHandler::class,
        ));

        $types->register(new RouteType(
            type: 'category',
            model: Category::class,
            formatter: Slug::class,
            handler: PageHandler::class,
            acceptsTail: true,
            onConflict: OnConflict::Suffix,
        ));

        // Deliberately a bare slug under `fail`: an article that could never collide — which is
        // what `SlugId` guarantees — would leave the restore-onto-a-taken-address case untested.
        $types->register(new RouteType(
            type: 'article',
            model: Article::class,
            formatter: Slug::class,
        ));

        $types->register(new RouteType(
            type: 'product',
            model: Product::class,
            formatter: SlugSku::class,
            onConflict: OnConflict::Suffix,
        ));
    }

    /**
     * @param  list<string>  $codes  The first one is the default.
     */
    protected function useLocales(array $codes): void
    {
        $locales = [];

        foreach ($codes as $index => $code) {
            $locales[] = ['code' => $code, 'default' => $index === 0];
        }

        $this->app['config']->set('webx-localization.locales', $locales);
        $this->app->make(Locales::class)->forget();
    }

    /** A page saved into the tree, with the observer doing its work on the way. */
    protected function page(string $slug, ?Page $parent = null): Page
    {
        $page = new Page(['title' => ucfirst($slug), 'slug' => $slug]);

        $parent === null ? $page->saveAsRoot() : $page->appendTo($parent);

        return $page->refresh();
    }
}
