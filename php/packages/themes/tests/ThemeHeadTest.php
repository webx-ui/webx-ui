<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Contracts\HeadPart;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

class ThemeHeadTest extends TestCase
{
    private string $public;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->public = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-public-'.bin2hex(random_bytes(6));
        mkdir($this->public);
        $app->usePublicPath($this->public);
        $app['config']->set('app.debug', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->public);

        parent::tearDown();
    }

    #[Test]
    public function it_prints_the_merged_tokens_inline(): void
    {
        $html = Blade::render('@webxTheme');

        $this->assertStringContainsString("<style data-webx-theme>\n:root {\n  --site-color-bg: #ffffff;", $html);
        $this->assertStringContainsString('--site-color-accent: #c0392b;', $html);
    }

    #[Test]
    public function a_packaged_layer_is_linked_once_it_is_synced(): void
    {
        $this->assertStringContainsString('<!-- webx-themes: webx-ui/theme-fixture is not published: php artisan webx:theme:sync -->', Blade::render('@webxTheme'));

        $this->artisan('webx:theme:sync')->assertSuccessful();

        $hash = (string) app(ThemeAssets::class)->current($this->package());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $hash);
        $this->assertFileExists("{$this->public}/themes/webx-ui/theme-fixture/{$hash}/theme.css");
        $this->assertFileExists("{$this->public}/themes/webx-ui/theme-fixture/{$hash}/assets/img/dot.svg");

        $html = Blade::render('@webxTheme');
        $this->assertStringContainsString("<link rel=\"stylesheet\" href=\"http://localhost/themes/webx-ui/theme-fixture/{$hash}/theme.css\">", $html);
        $this->assertStringNotContainsString('not published', $html);
    }

    #[Test]
    public function sync_leaves_unchanged_files_alone_and_keeps_one_previous_copy(): void
    {
        $assets = app(ThemeAssets::class);
        $package = $this->package();
        $directory = $assets->directory($package);

        $this->assertSame('published', $assets->publish($package));
        $first = $assets->current($package);
        $this->assertSame('unchanged', $assets->publish($package));

        // Two copies from earlier releases: the one pages linked to a moment ago stays, the other goes.
        file_put_contents($directory.'/current', 'aaaaaaaaaaaa');
        mkdir($directory.'/aaaaaaaaaaaa');
        mkdir($directory.'/bbbbbbbbbbbb');

        $this->assertSame('published', $assets->publish($package));
        $this->assertSame($first, $assets->current($package));
        $this->assertDirectoryExists($directory.'/aaaaaaaaaaaa');
        $this->assertDirectoryDoesNotExist($directory.'/bbbbbbbbbbbb');
    }

    /** What a package below the themes prints comes after the tokens and before every layer. */
    #[Test]
    public function a_head_part_is_first_in_the_cascade(): void
    {
        $this->app->instance('fixture.head', new class implements HeadPart
        {
            public function head(): string
            {
                return '<!-- the widgets -->';
            }
        });
        $this->app->tag(['fixture.head'], HeadPart::TAG);
        $this->app->make(ThemeAssets::class)->publish($this->package());

        $html = Blade::render('@webxTheme');
        $tokens = strpos($html, '<style data-webx-theme>');
        $part = strpos($html, '<!-- the widgets -->');
        $layer = strpos($html, '/themes/webx-ui/theme-fixture/');

        $this->assertNotFalse($tokens);
        $this->assertNotFalse($part);
        $this->assertNotFalse($layer);
        $this->assertLessThan($part, $tokens);
        $this->assertLessThan($layer, $part, 'the bottom of the chain comes first');
    }

    #[Test]
    public function sync_publishes_the_bottom_layers_next_to_the_themes(): void
    {
        $layer = $this->app->make(BottomLayers::class)->add('fixture/bottom-layer', self::fixture('bottom-layer'));

        $this->artisan('webx:theme:sync')
            ->expectsOutputToContain('fixture/bottom-layer')
            ->assertSuccessful();

        $hash = (string) $this->app->make(ThemeAssets::class)->current($layer);
        $this->assertFileExists("{$this->public}/themes/fixture/bottom-layer/{$hash}/bottom.css");
    }

    #[Test]
    public function a_dry_run_touches_nothing(): void
    {
        $this->artisan('webx:theme:sync', ['--dry-run' => true])
            ->expectsOutputToContain('would publish')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->public.'/themes');
    }

    #[Test]
    public function a_local_layer_comes_from_the_sites_vite_build_after_the_packages(): void
    {
        $this->app->setBasePath(self::fixture('site'));

        $this->assertStringContainsString('<!-- webx-themes: the local theme is not built: npm run build (theme/src/css/theme.css) -->', Blade::render('@webxTheme'));

        mkdir($this->public.'/build');
        file_put_contents($this->public.'/build/manifest.json', (string) json_encode([
            'theme/src/css/theme.css' => ['file' => 'assets/theme-abc123.css', 'src' => 'theme/src/css/theme.css', 'isEntry' => true],
        ]));
        $this->app->make(ThemeAssets::class)->publish($this->package());

        $html = Blade::render('@webxTheme');
        $package = strpos($html, '/themes/webx-ui/theme-fixture/');
        $local = strpos($html, 'build/assets/theme-abc123.css');

        $this->assertNotFalse($package);
        $this->assertNotFalse($local);
        $this->assertLessThan($local, $package, 'the local theme comes later in the cascade');
    }

    #[Test]
    public function the_hints_are_for_debug_only(): void
    {
        config()->set('app.debug', false);

        $this->assertStringNotContainsString('<!--', Blade::render('@webxTheme'));
    }

    private function package(): ThemeManifest
    {
        return app(ThemeChain::class)->layers[1];
    }
}
