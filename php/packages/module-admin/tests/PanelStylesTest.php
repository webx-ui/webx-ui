<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Support\PanelStyles;

/**
 * What a page outside the Vue app links of the panel's build: its stylesheets, never its scripts.
 */
final class PanelStylesTest extends TestCase
{
    private string $public;

    protected function setUp(): void
    {
        parent::setUp();

        $this->public = sys_get_temp_dir().'/webx-panel-styles-'.uniqid();
        (new Filesystem)->ensureDirectoryExists($this->public.'/build');
        $this->app->usePublicPath($this->public);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->public);

        parent::tearDown();
    }

    #[Test]
    public function the_stylesheets_among_the_assets_are_linked_and_the_scripts_are_not(): void
    {
        $this->app['config']->set('webx-admin.assets', ['/webx/webx.css?v=3', '/webx/webx.js']);

        $this->assertSame(['/webx/webx.css?v=3'], $this->app->make(PanelStyles::class)->urls());
    }

    #[Test]
    public function a_vite_build_gives_the_css_of_its_entry_and_of_every_chunk_it_imports(): void
    {
        $this->app['config']->set('webx-admin.vite', ['resources/js/admin.ts']);

        file_put_contents($this->public.'/build/manifest.json', (string) json_encode([
            'resources/js/admin.ts' => ['file' => 'assets/admin-1.js', 'css' => ['assets/admin-1.css'], 'imports' => ['_core.js']],
            '_core.js' => ['file' => 'assets/core-2.js', 'css' => ['assets/core-2.css'], 'imports' => ['resources/js/admin.ts']],
            'resources/js/site.ts' => ['file' => 'assets/site-3.js', 'css' => ['assets/site-3.css']],
        ]));

        $this->assertSame(['/build/assets/admin-1.css', '/build/assets/core-2.css'], $this->app->make(PanelStyles::class)->urls());
    }

    /** The dev server injects CSS from JavaScript; there is no file to link, and the tokens stand alone. */
    #[Test]
    public function nothing_comes_from_the_manifest_while_the_dev_server_runs_or_before_a_build(): void
    {
        $this->app['config']->set('webx-admin.vite', ['resources/js/admin.ts']);

        $beforeTheBuild = $this->app->make(PanelStyles::class)->urls();

        file_put_contents($this->public.'/build/manifest.json', (string) json_encode([
            'resources/js/admin.ts' => ['file' => 'assets/admin-1.js', 'css' => ['assets/admin-1.css']],
        ]));
        file_put_contents($this->public.'/hot', 'http://localhost:5173');

        $this->assertSame(['before' => [], 'dev server' => []], [
            'before' => $beforeTheBuild,
            'dev server' => $this->app->make(PanelStyles::class)->urls(),
        ]);
    }
}
