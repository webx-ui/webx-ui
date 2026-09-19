<?php

declare(strict_types=1);

namespace WebxUi\Blog;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WebxUi\Blog\Handlers\ArticleHandler;
use WebxUi\Blog\Handlers\RubricHandler;
use WebxUi\Blog\Handlers\TagHandler;
use WebxUi\Blog\Http\Controllers\FeedController;
use WebxUi\Blog\Http\Controllers\RssController;
use WebxUi\Blog\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Seo\TagSource;
use WebxUi\Routing\Formatters\Prefixed;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\OnConflict;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Rendering\SeoSources;

/**
 * Three kinds of entity that have addresses, one entity that is made of blocks, two routes that
 * are not entities at all, and one source of SEO. Almost nothing else — which is the same shape
 * `module-pages` has, and for the same reason: the parts were written before this module was.
 */
class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-blog.php', 'webx-blog');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-blog');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-blog');

        $this->registerRouteTypes();
        $this->registerFeedRoutes();
        $this->registerBlockEntity();
        $this->registerSeoSource();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-blog.php' => config_path('webx-blog.php'),
        ], 'webx-blog-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-blog'),
        ], 'webx-blog-lang');

        // The five public views ship as the least markup that works, and are meant to be
        // published and rewritten: a blog is the part of a site that looks like the site.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-blog'),
        ], 'webx-blog-views');
    }

    /**
     * The three types, all under one prefix and all in one flat namespace (§4).
     *
     * `Fail` on every one of them, and it is the same argument each time: an address is chosen
     * deliberately, and one that quietly became `remont-2` is a mistake somebody finds months
     * later in a search result. A rubric called "Repairs" and an article slugged `remont`
     * colliding is therefore an error under the field rather than a second address — two
     * different screens at one address is not what the editor meant either way.
     */
    private function registerRouteTypes(): void
    {
        $types = $this->app->make(RouteTypes::class);
        $prefix = $this->prefix();

        // Read once, here, rather than on every format: a formatter has to be a pure function of
        // the entity, so that the observer and `webx:routes:rebuild` cannot disagree about where
        // an article lives. Changing the prefix is that command, not a restart.
        $flat = $prefix === '' ? new Slug : new Prefixed($prefix, Slug::class);
        $tags = new Prefixed(UrlNormaliser::join($prefix, 'tag'), Slug::class);

        $types->register(new RouteType(
            type: 'article',
            model: Article::class,
            formatter: $flat,
            handler: ArticleHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));

        $types->register(new RouteType(
            type: 'rubric',
            model: Rubric::class,
            formatter: $flat,
            handler: RubricHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));

        $types->register(new RouteType(
            type: 'tag',
            model: Tag::class,
            formatter: $tags,
            handler: TagHandler::class,
            acceptsTail: false,
            onConflict: OnConflict::Fail,
        ));
    }

    /**
     * The two addresses the blog has that are not entities: the feed and the RSS (§2.11).
     *
     * Ordinary routes, so they win before the registry's fallback is reached and `Reserved`
     * closes them to pages without being told. With no prefix the feed is not registered at
     * all — `/` belongs to the site, and a list of articles on it is a page the site writes —
     * while the RSS still gets an address, because a site without one has no feed to subscribe
     * to and nowhere else to put it.
     *
     * Each is registered twice where the site puts the language in the path. The prefixed copy
     * would otherwise match `anything/blog`, which is what {@see OneSpellingPerAddress} is in
     * front of it for.
     */
    private function registerFeedRoutes(): void
    {
        $prefix = $this->prefix();
        $middleware = $this->middleware();

        $routes = [
            ['path' => UrlNormaliser::join($prefix, 'rss'), 'action' => RssController::class, 'name' => 'webx.blog.rss'],
        ];

        if ($prefix !== '') {
            array_unshift($routes, ['path' => $prefix, 'action' => FeedController::class, 'name' => 'webx.blog.feed']);
        }

        foreach ($routes as $route) {
            Route::get($route['path'], $route['action'])
                ->middleware($middleware)
                ->name($route['name']);

            if (! $this->localeIsInThePath()) {
                continue;
            }

            Route::get('{'.OneSpellingPerAddress::PARAMETER.'}/'.$route['path'], $route['action'])
                ->middleware([...$middleware, OneSpellingPerAddress::class])
                ->name($route['name'].'.localised');
        }
    }

    /**
     * Articles are an entity made of blocks, which is what `webx:blocks:bundles --warm` needs to
     * be told: the config of `module-blocks` cannot know them, so the module that has them adds
     * itself.
     */
    private function registerBlockEntity(): void
    {
        /** @var Config $config */
        $config = $this->app->make('config');

        /** @var list<string> $entities */
        $entities = (array) $config->get('webx-blocks.entities', []);

        if (! in_array(Article::class, $entities, true)) {
            $config->set('webx-blocks.entities', [...$entities, Article::class]);
        }
    }

    /** What a tag page says about itself, and whether it is in the index at all (§12). */
    private function registerSeoSource(): void
    {
        $this->app->make(SeoSources::class)->register($this->app->make(TagSource::class));
    }

    private function prefix(): string
    {
        return UrlNormaliser::key((string) $this->app->make('config')->get('webx-blog.prefix', 'blog'));
    }

    /**
     * @return list<string>
     */
    private function middleware(): array
    {
        /** @var list<string> $middleware */
        $middleware = (array) $this->app->make('config')->get('webx-blog.middleware', ['web', 'webx.locale']);

        return array_values($middleware);
    }

    private function localeIsInThePath(): bool
    {
        return (string) $this->app->make('config')->get('webx-localization.strategy', 'prefix') === 'prefix';
    }
}
