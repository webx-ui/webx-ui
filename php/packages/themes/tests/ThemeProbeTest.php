<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Testing\ThemeProbe;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeLook;

/**
 * What `Testing\ThemeContract` holds a theme to (spec §18), said red where it has to be: the
 * contract is only worth its green on theme-default and a fresh theme if it fails on a broken one.
 * And {@see ThemeLook}, which `extract` and `eject` trust to say the site did not change.
 */
final class ThemeProbeTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->files->deleteDirectory($this->app->basePath('probed-theme'));
        $this->files->copyDirectory(self::fixture('site/theme'), $this->app->basePath('probed-theme'));
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->app->basePath('probed-theme'));

        parent::tearDown();
    }

    #[Test]
    public function a_chain_without_a_value_for_every_token_and_without_the_layout_directives_is_red(): void
    {
        $probe = $this->probe();

        $this->assertContains('color-surface', $probe->unvalued(), 'the fixture below gives only a few tokens');
        $this->assertCount(5, $probe->layoutProblems());
        $this->assertStringContainsString('no @webxTheme', $probe->layoutProblems()[0]);
        $this->assertStringContainsString('<x-webx-blocks::region name="footer">', $probe->layoutProblems()[4]);
    }

    #[Test]
    public function a_view_that_does_not_compile_and_a_component_the_theme_lacks_are_named(): void
    {
        $this->files->put($this->app->basePath('probed-theme/views/broken.blade.php'), "@if (true)\n<p>never closed</p>\n");
        $this->files->put($this->app->basePath('probed-theme/views/stray.blade.php'), '<x-card-that-is-not-there />');
        $this->files->put($this->app->basePath('probed-theme/views/module.blade.php'), '<x-webx-absent::thing />');

        $failures = implode("\n", $this->probe()->uncompiled());

        $this->assertStringContainsString('broken.blade.php', $failures);
        $this->assertStringContainsString('stray.blade.php', $failures);
        $this->assertStringNotContainsString('module.blade.php', $failures, "a module's component is not the theme's to have");
    }

    #[Test]
    public function text_that_does_not_read_in_a_preset_is_named_with_its_ratio(): void
    {
        $this->files->put($this->app->basePath('probed-theme/tokens.json'), json_encode([
            'defaults' => ['color-text' => '#111111', 'color-bg' => '#ffffff'],
            'presets' => ['fog' => ['title' => 'Fog', 'tokens' => ['color-text' => '#bbbbbb']]],
        ]));

        $failures = $this->probe()->unreadable();

        $this->assertContains('preset fog: color-text on color-bg is 1.92:1, below 4.5:1', $failures);
        $this->assertSame([], array_filter($failures, static fn (string $line): bool => str_starts_with($line, 'defaults: color-text on color-bg')));
    }

    #[Test]
    public function text_that_does_not_read_in_the_dark_scheme_is_named_too(): void
    {
        $this->files->put($this->app->basePath('probed-theme/tokens.json'), json_encode([
            'defaults' => ['color-text' => '#111111', 'color-bg' => '#ffffff'],
            'schemes' => ['dark' => ['color-bg' => '#0d0f0e']],
        ]));

        $this->assertStringContainsString('dark scheme: color-text on color-bg is 1.0', implode("\n", $this->probe()->unreadable()));
    }

    #[Test]
    public function the_look_names_what_changed_between_two_chains(): void
    {
        $locator = $this->app->make(ThemeLocator::class);
        $before = ThemeLook::of(ThemeChain::resolve($this->app->basePath('probed-theme'), $locator));

        $this->files->put($this->app->basePath('probed-theme/tokens.json'), '{"defaults": {"color-accent": "#000000", "radius-md": "4px"}}');
        $this->files->put($this->app->basePath('probed-theme/views/local-wins.blade.php'), 'changed');
        $this->files->delete($this->app->basePath('probed-theme/icons/star.svg'));

        $differences = ThemeLook::differences($before, ThemeLook::of(ThemeChain::resolve($this->app->basePath('probed-theme'), $locator)));

        $this->assertContains('token color-accent: changed', $differences);
        $this->assertContains('view local-wins.blade.php: changed', $differences);
        $this->assertContains('icon star: gone', $differences);
        $this->assertNotContains('token radius-md: changed', $differences);
    }

    private function probe(): ThemeProbe
    {
        return new ThemeProbe($this->app->basePath('probed-theme'), $this->app);
    }
}
