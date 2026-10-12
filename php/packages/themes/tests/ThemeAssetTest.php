<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Checks\AssetCheck;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;

/**
 * `theme_asset()` (spec §13.2): a file under `assets/` of the highest layer that has it, as sync
 * published it — a package's under its name, the local theme's under `local-<directory>`; and
 * `webx:theme:check` names a `theme_asset('…')` no layer answers.
 */
final class ThemeAssetTest extends TestCase
{
    private string $public;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->public = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-assets-'.bin2hex(random_bytes(6));
        mkdir($this->public);
        $app->usePublicPath($this->public);
        $app['config']->set('app.debug', false);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->public);

        parent::tearDown();
    }

    #[Test]
    public function the_highest_layer_with_the_file_answers_once_synced(): void
    {
        $this->assertSame('', theme_asset('img/wave.svg'));

        $this->artisan('webx:theme:sync')
            ->expectsOutputToContain("local, built by the site's Vite; assets/ published")
            ->assertSuccessful();

        $chain = $this->app->make(ThemeChain::class);
        $assets = $this->app->make(ThemeAssets::class);
        [$local, $package] = $chain->layers;
        $mine = (string) $assets->current($local);
        $theirs = (string) $assets->current($package);

        // The local theme's own picture wins by path; one only the package has comes from the package.
        $this->assertSame("http://localhost/themes/local-theme/{$mine}/assets/img/wave.svg", theme_asset('img/wave.svg'));
        $this->assertStringContainsString('data-layer="local"', (string) file_get_contents("{$this->public}/themes/local-theme/{$mine}/assets/img/wave.svg"));
        $this->assertSame("http://localhost/themes/webx-ui/theme-fixture/{$theirs}/assets/img/dot.svg", theme_asset('/img/dot.svg'));

        // A local layer publishes its assets/ and nothing else: its CSS is the site's Vite's.
        $this->assertFileDoesNotExist("{$this->public}/themes/local-theme/{$mine}/theme.css");

        $this->assertStringContainsString("themes/local-theme/{$mine}/assets/img/wave.svg", Blade::render('<x-picture />'));

        $this->artisan('webx:theme:sync')
            ->expectsOutputToContain("local, built by the site's Vite; assets/ unchanged")
            ->assertSuccessful();
    }

    #[Test]
    public function a_path_no_layer_has_or_that_leaves_assets_is_empty_and_said_in_debug(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        foreach (['img/none.svg', '../theme.json', 'img//wave.svg', 'https://example.com/a.svg', ''] as $path) {
            $this->assertSame('', theme_asset($path), $path);
        }

        $this->app['config']->set('app.debug', true);
        $log = Log::spy();

        theme_asset('img/none.svg');

        $log->shouldHaveReceived('warning')->once()->withArgs(static fn (string $message): bool => $message === "theme_asset('img/none.svg'): no layer of the theme has assets/img/none.svg");
    }

    #[Test]
    public function a_file_added_after_the_sync_waits_for_the_next_one_rather_than_the_lower_copy(): void
    {
        $this->artisan('webx:theme:sync')->assertSuccessful();

        $added = self::fixture('site/theme/assets/img/dot.svg');
        file_put_contents($added, '<svg xmlns="http://www.w3.org/2000/svg" data-layer="local"/>');

        try {
            $this->app['config']->set('app.debug', true);
            $log = Log::spy();

            $this->assertSame('', theme_asset('img/dot.svg'));
            $log->shouldHaveReceived('warning')->once()->withArgs(static fn (string $message): bool => str_contains($message, 'is not published: php artisan webx:theme:sync'));

            $this->artisan('webx:theme:sync')->assertSuccessful();
            $this->assertStringContainsString('/themes/local-theme/', theme_asset('img/dot.svg'));
        } finally {
            unlink($added);
        }
    }

    #[Test]
    public function a_file_name_is_encoded_in_the_address(): void
    {
        $spaced = self::fixture('site/theme/assets/img/hero image.svg');
        file_put_contents($spaced, '<svg xmlns="http://www.w3.org/2000/svg"/>');

        try {
            $this->artisan('webx:theme:sync')->assertSuccessful();

            $this->assertStringEndsWith('/assets/img/hero%20image.svg', theme_asset('img/hero image.svg'));
        } finally {
            unlink($spaced);
        }
    }

    #[Test]
    public function only_a_literal_path_outside_a_blade_comment_is_read(): void
    {
        $calls = AssetCheck::calls((string) file_get_contents(self::fixture('assets-theme/views/components/banner.blade.php')));

        $this->assertSame([
            ['line' => 2, 'path' => 'img/gone.svg'],
            ['line' => 3, 'path' => 'img/dot.svg'],
            ['line' => 4, 'path' => 'img/missing.png'],
            ['line' => 5, 'path' => '../.env'],
        ], $calls);
    }

    #[Test]
    public function the_check_names_an_asset_no_layer_has(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('assets-theme')])
            ->expectsOutputToContain("banner.blade.php:2  theme_asset('img/gone.svg') — no layer of the chain has assets/img/gone.svg")
            ->expectsOutputToContain("banner.blade.php:4  theme_asset('img/missing.png') — no layer of the chain has assets/img/missing.png")
            ->expectsOutputToContain("banner.blade.php:5  theme_asset('../.env') — not a path under assets/ of a layer")
            ->expectsOutputToContain('3 findings')
            ->assertFailed();
    }

    #[Test]
    public function a_clean_chain_counts_its_asset_calls(): void
    {
        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('2 theme_asset() calls, every one a file of the chain')
            ->assertSuccessful();
    }
}
