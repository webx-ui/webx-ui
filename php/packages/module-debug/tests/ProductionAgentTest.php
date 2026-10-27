<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse as HttpResponse;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Debug\Access;
use WebxUi\Debug\Collector;
use WebxUi\Debug\DebugServiceProvider;
use WebxUi\Debug\Doctor\ProductionCheck;
use WebxUi\Debug\Http\CollectLate;
use WebxUi\Debug\Http\StartCollecting;
use WebxUi\Debug\Mcp\PageFetcher;
use WebxUi\Debug\Reports;
use WebxUi\Debug\Support\Masker;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Production with `WEBX_DEBUG_MCP_IN_PRODUCTION` (§3.3 of the debug bar spec): `debug_page` and
 * only what it needs — its token opens collecting, nothing else does; no bar, no cookie, no API of
 * reports; and a report keeps no value of any statement.
 */
final class ProductionAgentTest extends TestCase
{
    /** What the page last answered the agent's request with. */
    private ?string $lastBody = null;

    /**
     * @param  Application  $app
     */
    protected function resolveApplicationCore($app): void
    {
        $app->detectEnvironment(static fn (): string => 'production');

        // The way a stand says it, before any provider registers: the config reads it then.
        putenv('WEBX_DEBUG_MCP_IN_PRODUCTION=true');
    }

    protected function tearDown(): void
    {
        putenv('WEBX_DEBUG_MCP_IN_PRODUCTION');

        parent::tearDown();
    }

    /**
     * A page that writes a visitor's address into its log, besides the pages of the site.
     *
     * @param  Router  $router
     */
    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);

        $router->middleware('web')->get('/order', static function () {
            DB::table('cms_users')->where('email', 'visitor@example.test')->first();
            Log::warning('Order placed by visitor@example.test', ['email' => 'visitor@example.test', 'items' => ['count' => 2]]);

            return response('<html><body>order</body></html>');
        });
    }

    #[Test]
    public function only_the_agents_path_is_registered(): void
    {
        $this->assertTrue($this->app->environment('production'));
        $this->assertTrue($this->app->bound(Collector::class));
        $this->assertTrue($this->app->make(ModuleRegistry::class)->has('debug'));

        $tools = $this->app->make(ToolRegistry::class)->toolsOf('debug');
        $this->assertCount(1, $tools);
        $this->assertSame('debug_page', $tools[0]->fullName());
        $this->assertSame('debug:read', $tools[0]->scope());
        $this->assertSame(['debug.view'], $tools[0]->permissions());

        $this->assertContains(StartCollecting::class, $this->app->make(Kernel::class)->getGlobalMiddleware());
        $this->assertNotContains(CollectLate::class, $this->app['router']->getMiddlewareGroups()['web'] ?? []);
        $this->assertFalse(Route::has('webx.debug.bar'));
        $this->assertFalse(Route::has('webx.debug.reports.index'));
        $this->assertFalse(Route::has('webx.debug.reports.show'));
    }

    #[Test]
    public function an_administrator_with_debug_view_measures_a_page(): void
    {
        $this->seedArticles();
        $admin = $this->admin(super: false, permissions: ['debug.view']);

        $report = $this->content($this->agent(['url' => '/articles'], $admin));

        $this->assertSame(200, $report['status']);
        $this->assertSame(1, $report['summary']['repeated']);
        $this->assertSame(3, $report['repeated'][0]['count']);
        $this->assertNotNull($this->app->make(Reports::class)->find($report['id'], $admin->getKey()));

        // The page the agent got is a visitor's.
        $this->assertSame('<html><body>Article 1 cover-1.jpg, Article 2 cover-2.jpg, Article 3 cover-3.jpg</body></html>', $this->lastBody);
    }

    #[Test]
    public function without_the_permission_the_tool_refuses(): void
    {
        $this->agent(['url' => '/plain'], $this->admin(super: false, permissions: ['pages.manage']))
            ->assertHasErrors(['[debug.view]']);
    }

    #[Test]
    public function every_value_is_masked(): void
    {
        $report = $this->content($this->agent(['url' => '/order', 'include' => ['queries', 'other', 'log', 'request']]));
        $packed = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('visitor@example.test', $packed);

        $statement = null;

        foreach ($report['queries'] as $query) {
            if (($query['kind'] ?? '') === 'query' && str_contains($query['sql'], 'from "cms_users"')) {
                $statement = $query;
            }
        }

        $this->assertNotNull($statement, 'The statement of the page is in the report.');
        // The shape and the place stay: they are what the agent came for.
        $this->assertSame([Masker::MASK], $statement['bindings']);
        $this->assertStringContainsString('"email" = \''.Masker::MASK.'\'', $statement['inline']);

        $line = $report['other']['log'][0];
        $this->assertSame('warning', $line['level']);
        $this->assertSame(Masker::MASK, $line['message']);
        $this->assertSame(['email' => Masker::MASK, 'items' => ['count' => Masker::MASK]], $line['context']);

        foreach ($report['request']['session'] as $value) {
            $this->assertSame(Masker::MASK, $value);
        }
    }

    #[Test]
    public function a_visitor_gets_the_page_untouched(): void
    {
        $plain = $this->get('/plain');

        $this->assertUntouched($plain);
    }

    #[Test]
    public function a_forged_token_starts_nothing_and_changes_nothing(): void
    {
        $queries = $this->listeners(QueryExecuted::class);

        $forged = $this->withHeaders([Access::HEADER => 'forged'])->get('/plain');
        $this->flushHeaders();

        $this->assertUntouched($forged);
        $this->assertFalse($this->app->make(Collector::class)->active);
        $this->assertSame($queries, $this->listeners(QueryExecuted::class), 'A forged token subscribes no collector.');
        $this->assertSame($this->headerNames($this->get('/plain')), $this->headerNames($forged));
    }

    #[Test]
    public function a_token_is_good_once(): void
    {
        $admin = $this->admin();
        $issued = $this->app->make(Access::class)->issue($admin);

        $first = $this->withHeaders([Access::HEADER => $issued['token']])->get('/plain');
        $this->assertUntouched($first);
        $this->assertNotNull($this->app->make(Reports::class)->find($issued['id'], $admin->getKey()));

        cache()->forget('webx:debug:'.$issued['id']);

        $this->assertUntouched($this->withHeaders([Access::HEADER => $issued['token']])->get('/plain'));
        $this->assertNull($this->app->make(Reports::class)->find($issued['id'], $admin->getKey()));
    }

    #[Test]
    public function the_bar_the_cookie_and_the_reports_stay_off_for_an_administrator(): void
    {
        $admin = $this->admin();

        $page = $this->asAdmin($admin, '/plain');
        $this->assertUntouched($page);
        $page->assertCookieMissing(Access::COOKIE);
        $this->assertFalse($this->app->make(Collector::class)->active);

        // Signed in without the cookie: no late start, no cookie set either.
        $this->asAdmin($admin, '/plain', marked: false)->assertCookieMissing(Access::COOKIE);

        $this->actingAs($admin, 'cms')->getJson('https://example.test/api/cms/debug/reports')->assertNotFound();
        $this->assertNotSame(200, $this->actingAs($admin, 'cms')->getJson('https://example.test/api/cms/debug/reports/01J0000000000000000000000A')->getStatusCode());
    }

    #[Test]
    public function the_doctor_says_it_is_open(): void
    {
        $this->assertContains(ProductionCheck::class, $this->app->make(DoctorChecks::class)->all());

        $said = $this->app->make(ProductionCheck::class)->run();

        $this->assertCount(1, $said);
        $this->assertSame(Diagnosis::WARN, $said[0]->state);
        $this->assertStringContainsString('WEBX_DEBUG_MCP_IN_PRODUCTION', $said[0]->detail);
        $this->assertFalse($said[0]->stops(true), 'A stand measured on purpose deploys under --strict.');
    }

    #[Test]
    public function the_variable_counts_where_a_published_config_has_no_key_for_it(): void
    {
        $this->assertFalse(DebugServiceProvider::openInProduction(false));
        $this->assertTrue(DebugServiceProvider::openInProduction(true));

        $this->assertTrue(DebugServiceProvider::openInProduction(null));

        putenv('WEBX_DEBUG_MCP_IN_PRODUCTION=false');
        $this->assertFalse(DebugServiceProvider::openInProduction(null));
    }

    /**
     * Nothing of the module in a page's answer: the body as the site made it, no cache header or
     * Vary of ours.
     *
     * @param  HttpResponse<Response>  $response
     */
    private function assertUntouched(HttpResponse $response): void
    {
        $response->assertOk();
        $this->assertSame('<html><body><h1>Hello</h1></body></html>', $response->getContent());
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNotContains('Cookie', $response->getVary());
    }

    /**
     * @param  HttpResponse<Response>  $response
     * @return list<string>
     */
    private function headerNames(HttpResponse $response): array
    {
        $names = array_keys($response->headers->all());
        sort($names);

        return $names;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(array $arguments, ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('debug_page'));
        $this->app->instance(PageFetcher::class, new class($this) implements PageFetcher
        {
            public function __construct(private readonly ProductionAgentTest $test) {}

            public function fetch(string $url, array $headers): array
            {
                return $this->test->answer($url, $headers);
            }
        });

        return WebxServer::actingAs($as ?? $this->admin(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{status: int|null, ms: int, error: string|null}
     */
    public function answer(string $url, array $headers): array
    {
        $response = $this->withHeaders($headers)->get($url);
        $this->flushHeaders();
        $this->lastBody = (string) $response->getContent();

        return ['status' => $response->getStatusCode(), 'ms' => 1, 'error' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
