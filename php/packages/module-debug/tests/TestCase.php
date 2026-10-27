<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as Orchestra;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Links\SiteRoutes;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Regions;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Cache\CacheGroups;
use WebxUi\Debug\Access;
use WebxUi\Debug\DebugServiceProvider;
use WebxUi\Debug\Reports;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;

abstract class TestCase extends Orchestra
{
    use MigratesOnce;

    protected function setUp(): void
    {
        parent::setUp();

        // A test that reaches the network waits on it; whatever it calls is faked.
        Http::preventStrayRequests();
    }

    /**
     * The bar the way a site has it: the panel and its administrators, MCP, and the blocks for
     * regions and their blocks to be drawn.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
            DebugServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->migrateOnce();

        Schema::create('demo_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('cover_id')->nullable();
        });

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->longText('payload')->nullable();
            });
        }

        Schema::create('demo_covers', function (Blueprint $table): void {
            $table->id();
            $table->string('path');
        });
    }

    /**
     * The pages of a small site: plain HTML, a list with an N+1, a page of blocks and a region,
     * a page that reads the versioned cache, and the kinds of response the bar never touches.
     *
     * @param  Router  $router
     */
    protected function defineWebRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router): void {
            $router->get('/plain', static fn () => response('<html><body><h1>Hello</h1></body></html>'));

            $router->get('/articles', static function () {
                $titles = [];

                // An N+1 the way it happens: a list, and a question per row.
                foreach (DB::table('demo_articles')->orderBy('id')->get() as $article) {
                    $cover = DB::table('demo_covers')->where('id', $article->cover_id)->first();
                    $titles[] = $article->title.' '.($cover->path ?? '');
                }

                return response('<html><body>'.e(implode(', ', $titles)).'</body></html>');
            });

            $router->get('/view', static fn () => app('view')->file(__DIR__.'/Fixtures/views/page.blade.php'));

            $router->get('/secrets', static function () {
                DB::table('cms_users')->where('email', 'nobody@example.test')->where('password', 'hunter2-plain')->first();
                DB::table('sessions')->where('id', 'session-id-value')->first();

                return response('<html><body>secrets</body></html>');
            });

            $router->get('/blocks', function () {
                $html = (string) app(Renderer::class)->render([['key' => 'k1', 'type' => 'hero', 'values' => ['title' => 'Welcome']]]);
                $region = (string) app(Regions::class)->render('undeclared', [], 'fallback');

                return response('<html><body>'.$html.$region.'</body></html>');
            });

            $router->get('/cached', static function () {
                $groups = app(CacheGroups::class);
                $groups->declare('demo');
                $groups->remember('demo.list', ['demo'], static fn (): string => 'value');
                $groups->remember('demo.list', ['demo'], static fn (): string => 'value');

                return response('<html><body>cached</body></html>');
            });

            $router->get('/json', static fn () => response()->json(['ok' => true]));
            $router->get('/redirect', static fn () => redirect('/plain'));
            $router->get('/missing', static fn () => abort(404));
            $router->get('/no-body', static fn () => response('<p>fragment</p>'));
            $router->get('/_preview/thing', static fn () => response('<html><body>preview</body></html>'));
            // Service pages: the OAuth consent where Passport puts it, and a page a package marked.
            $router->get('/oauth/authorize', static fn () => response('<html><body>Allow the agent?</body></html>'));
            $router->group([SiteRoutes::SERVICE => true], static function () use ($router): void {
                $router->get('/account/sign-in', static fn () => response('<html><body>Sign in</body></html>'));
            });
        });
    }

    /**
     * Outside the `web` group: not a page of the site. (Testbench puts every route of
     * defineWebRoutes() in `web`.)
     *
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/bare', static fn () => response('<html><body>bare</body></html>'));
    }

    protected function tearDown(): void
    {
        if ($this->app !== null && $this->app->bound(TemplateCompiler::class)) {
            File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());
        }

        parent::tearDown();
    }

    /**
     * Somebody the panel lets in: a super administrator, or one with these permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function admin(bool $super = true, array $permissions = [], bool $active = true): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => "Admin {$count}",
            'email' => "admin-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => $super,
            'is_active' => $active,
        ]);

        if (! $super) {
            $role = Role::query()->create(['slug' => "role-{$count}", 'name' => 'Role', 'permissions' => $permissions]);
            $user->roles()->attach($role->getKey());
        }

        return $user;
    }

    /**
     * A page asked for by a signed-in administrator who carries the early-start cookie.
     *
     * @return TestResponse<Response>
     */
    protected function asAdmin(CmsUser $user, string $path, bool $marked = true): TestResponse
    {
        $request = $this->actingAs($user, 'cms');

        if ($marked) {
            $request = $request->withCookie(Access::COOKIE, (string) $user->getKey());
        }

        return $request->get($path);
    }

    /**
     * What the tag on a page says.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    protected function tagData(TestResponse $response): array
    {
        $this->assertSame(1, preg_match('/<script type="module" src="[^"]+" data-wx-debug="([^"]+)"><\/script>/', (string) $response->getContent(), $match), 'The page has no debug bar tag.');

        $data = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * The report a page's tag names, as its maker reads it.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    protected function reportOf(TestResponse $response, CmsUser $user): array
    {
        $report = $this->app->make(Reports::class)->find((string) $this->tagData($response)['id'], $user->getKey());
        $this->assertIsArray($report, 'The report of the page was not kept.');

        return $report;
    }

    /** How many listeners an event has right now. */
    protected function listeners(string $event): int
    {
        return count($this->app->make(Dispatcher::class)->getListeners($event));
    }

    /**
     * A block type with one published version, the way the panel would have made it.
     */
    protected function publish(string $slug, string $template): Block
    {
        $block = Block::query()->create(['slug' => $slug, 'title' => ucfirst($slug)]);

        preg_match_all('/\$([a-z][a-z0-9_]*)/i', $template, $found);
        $schema = array_map(
            static fn (string $id): array => ['id' => $id, 'type' => 'wx-input'],
            array_values(array_diff(array_unique($found[1]), ['block', 'entity', 'region', 'attributes', '__env'])),
        );

        $block->saveVersion(['template' => $template, 'schema' => $schema]);
        $block->publish();

        return $block->refresh();
    }

    /** Rows for the list with an N+1: three articles, each with a cover asked for one by one. */
    protected function seedArticles(): void
    {
        foreach ([1, 2, 3] as $id) {
            DB::table('demo_covers')->insert(['id' => $id, 'path' => "cover-{$id}.jpg"]);
            DB::table('demo_articles')->insert(['id' => $id, 'title' => "Article {$id}", 'cover_id' => $id]);
        }
    }
}
