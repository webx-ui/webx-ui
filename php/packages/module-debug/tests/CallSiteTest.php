<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Debug\Support\CallSite;
use WebxUi\Routing\Resolver;

/**
 * Where a statement came from (§7 of the debug bar spec): a package by its name, the site from
 * its root, a compiled view by its source, a compiled block template by its type.
 */
final class CallSiteTest extends TestCase
{
    #[Test]
    public function a_file_of_a_package_is_named_by_the_package(): void
    {
        $file = (string) (new \ReflectionClass(Resolver::class))->getFileName();

        $this->assertSame('webx-ui/routing · src/Resolver.php:57', $this->sites()->describe($file, 57)['label']);
    }

    #[Test]
    public function a_file_of_the_site_is_named_from_its_root(): void
    {
        $file = $this->app->basePath('app/Http/Controllers/Example.php');

        $this->assertSame('app/Http/Controllers/Example.php:12', $this->sites()->describe($file, 12)['label']);
    }

    #[Test]
    public function a_compiled_view_is_named_by_its_source(): void
    {
        $compiled = rtrim((string) config('view.compiled'), '/').'/0123456789abcdef.php';
        File::ensureDirectoryExists(dirname($compiled));
        $source = $this->app->basePath('resources/views/layouts/app.blade.php');
        File::put($compiled, "<?php echo 'x'; ?>\n<?php /**PATH {$source} ENDPATH**/ ?>");

        try {
            $this->assertSame('resources/views/layouts/app.blade.php', $this->sites()->describe($compiled, 3)['label']);
        } finally {
            File::delete($compiled);
        }
    }

    #[Test]
    public function a_compiled_block_template_is_named_by_its_type(): void
    {
        $file = rtrim((string) config('view.compiled'), '/').'/blocks/hero-12-0123456789ab.php';

        $this->assertSame('block hero (type v12)', $this->sites()->describe($file, 4)['label']);
    }

    #[Test]
    public function a_statement_in_a_block_says_which_block(): void
    {
        $this->publish('counter', '<p>{{ \Illuminate\Support\Facades\DB::table(\'demo_articles\')->count() }}</p>');
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'cms')->withCookie('wx_debug', '1')->get('/blocks-counter');
        $report = $this->reportOf($response, $admin);

        $count = array_values(array_filter($report['queries'], static fn (array $query): bool => ($query['kind'] ?? '') === 'query' && str_contains($query['sql'], 'demo_articles')));

        $this->assertSame('block counter (type v1)', $count[0]['site']);
        $this->assertSame('block counter (k1)', $count[0]['in']);
        $this->assertSame(1, $report['blocks'][0]['self_queries']);
    }

    /**
     * @param  Router  $router
     */
    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);

        $router->middleware('web')->get('/blocks-counter', static fn () => response(
            '<html><body>'.app(Renderer::class)->render([['key' => 'k1', 'type' => 'counter', 'values' => []]]).'</body></html>',
        ));
    }

    private function sites(): CallSite
    {
        return $this->app->make(CallSite::class);
    }
}
