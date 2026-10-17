<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Debug\Collector;
use WebxUi\Debug\Http\CollectLate;
use WebxUi\Debug\Http\StartCollecting;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * Production, whatever anybody says (§3.1 of the debug bar spec): the package is installed and
 * there is nothing of it — no middleware, no listener, no route, no module, no tool. Not even a
 * super administrator with the cookie and the switch on gets a byte of it.
 */
final class ProductionTest extends TestCase
{
    /**
     * Testbench puts every test case in `testing` before any provider registers; this one is
     * production from the start, the way a real site is.
     *
     * @param  Application  $app
     */
    protected function resolveApplicationCore($app): void
    {
        $app->detectEnvironment(static fn (): string => 'production');
    }

    #[Test]
    public function nothing_is_registered(): void
    {
        $this->assertTrue($this->app->environment('production'));
        $this->assertFalse($this->app->bound(Collector::class));
        $this->assertFalse(Route::has('webx.debug.bar'));
        $this->assertFalse(Route::has('webx.debug.reports.show'));
        $this->assertFalse($this->app->make(ModuleRegistry::class)->has('debug'));
        $this->assertSame([], $this->app->make(ToolRegistry::class)->toolsOf('debug'));

        $kernel = $this->app->make(Kernel::class);
        $this->assertNotContains(StartCollecting::class, $kernel->getGlobalMiddleware());
        $this->assertNotContains(CollectLate::class, $this->app['router']->getMiddlewareGroups()['web'] ?? []);
    }

    #[Test]
    public function a_super_administrator_with_the_cookie_gets_the_page_untouched(): void
    {
        $this->app['config']->set('webx-debug.enabled', true);
        $listeners = $this->listeners(RequestHandled::class);

        $response = $this->asAdmin($this->admin(), 'https://example.test/plain');

        $response->assertOk();
        $this->assertSame('<html><body><h1>Hello</h1></body></html>', $response->getContent());
        $this->assertSame($listeners, $this->listeners(RequestHandled::class));

        $this->actingAs($this->admin(), 'cms')->getJson('https://example.test/api/cms/debug/reports')->assertNotFound();
        // No route at all: the address falls to the site's own resolver, which only knows how
        // to spell it (the capitals of an id) — a redirect or a 404, never a report.
        $this->assertNotSame(200, $this->actingAs($this->admin(), 'cms')->getJson('https://example.test/api/cms/debug/reports/01J0000000000000000000000A')->getStatusCode());
    }
}
