<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Tests\Concerns\SiteSandbox;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeLook;

/**
 * `webx:theme:eject` (spec §14.3): a packaged theme becomes the site's own, and the site looks
 * the same — folded into the local theme above it, or taken whole by a site that stood on it.
 *
 * The packaged theme is the fixture `webx-ui/theme-fixture`, only ever read; the local theme is
 * a copy of the fixture site's under the project, made before boot because the chain is resolved
 * at boot.
 */
final class EjectCommandTest extends TestCase
{
    use SiteSandbox;

    /** The test that stands the site on the package itself, with no local theme. */
    private const string WHOLE = 'a_site_on_the_package_itself_takes_it_whole';

    private Filesystem $files;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $this->sandbox($app->basePath(), ['theme', 'site-theme']);

        if ($this->name() !== self::WHOLE) {
            (new Filesystem)->copyDirectory(self::fixture('site/theme'), $app->basePath('theme'));
        }

        parent::defineEnvironment($app);
    }

    protected function theme(): string
    {
        return $this->name() === self::WHOLE ? 'webx-ui/theme-fixture' : 'theme';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->files->put($this->app->basePath('.env'), "WEBX_THEME={$this->theme()}\n");
    }

    protected function tearDown(): void
    {
        $this->restoreSandbox();

        parent::tearDown();
    }

    #[Test]
    public function the_package_folds_into_the_local_theme_with_the_same_look(): void
    {
        $before = ThemeLook::of($this->app->make(ThemeChain::class));

        $this->artisan('webx:theme:eject')
            ->expectsOutputToContain('The same look')
            ->assertSuccessful();

        $theme = $this->app->basePath('theme');
        $this->assertSame([], ThemeLook::differences($before, ThemeLook::of(ThemeChain::resolve('theme', $this->app->make(ThemeLocator::class)))));

        // What the local theme had wins; what only the package had is the local theme's now.
        $this->assertSame('local', trim((string) $this->files->get($theme.'/views/local-wins.blade.php')));
        $this->assertSame('default', trim((string) $this->files->get($theme.'/views/default-wins.blade.php')));
        $this->assertFileExists($theme.'/blocks/note.json');
        $this->assertFileExists($theme.'/icons/arrow-right.svg');
        $this->assertFileExists($theme.'/assets/fonts/sans-latin.woff2');
        $this->assertFileExists($theme.'/lang/pl/theme.php');
        $this->assertFileDoesNotExist($theme.'/composer.json');
        $this->assertDirectoryDoesNotExist($theme.'/dist');

        $manifest = json_decode((string) $this->files->get($theme.'/theme.json'), true)['theme'];
        $this->assertSame('Local', $manifest['title']);
        $this->assertSame([], $manifest['uses'], 'the package stood on nothing, and so does the local theme now');
        $this->assertSame(['module-pages', 'module-blocks'], $manifest['requires']);
        $this->assertSame('Fixture Sans', $manifest['fonts'][0]['family']);

        $tokens = json_decode((string) $this->files->get($theme.'/tokens.json'), true);
        $this->assertSame('#c0392b', $tokens['defaults']['color-accent'], 'the local value wins');
        $this->assertSame('#111111', $tokens['defaults']['color-text'], "the package's value is kept where the local theme set none");
        $this->assertSame(['color-olive' => 'color'], $tokens['vocabulary']);
        $this->assertSame(['title' => 'Local night', 'tokens' => ['color-bg' => '#111418', 'color-text' => '#e8eaed', 'color-accent' => '#ff8a65']], $tokens['presets']['night']);
        $this->assertSame('Warm', $tokens['presets']['warm']['title']);

        // The words merged by key, the local theme's winning, in a file PHP reads back.
        $this->assertSame(
            ['default' => 'default', 'local' => 'local', 'site' => 'local', 'nested' => ['a' => 'default', 'b' => 'local']],
            require $theme.'/lang/en/theme.php',
        );

        // The package published no src/: its built stylesheet comes first, where it stood in the cascade.
        $this->assertFileExists($theme.'/src/css/theme-fixture/theme.css');
        $css = (string) $this->files->get($theme.'/src/css/theme.css');
        $this->assertSame(1, substr_count($css, "@import './theme-fixture/theme.css';"));
        $this->assertLessThan(strpos($css, ':where') ?: PHP_INT_MAX, strpos($css, '@import'));
    }

    #[Test]
    public function theme_default_is_never_ejected(): void
    {
        $this->artisan('webx:theme:eject', ['name' => 'webx-ui/theme-default'])
            ->expectsOutputToContain('is not ejected')
            ->assertFailed();

        $this->assertFileDoesNotExist($this->app->basePath('theme/views/default-wins.blade.php'));
    }

    #[Test]
    public function a_theme_that_is_not_in_the_chain_is_refused(): void
    {
        $this->artisan('webx:theme:eject', ['name' => 'acme/theme-elsewhere'])->assertFailed();

        $this->assertFileDoesNotExist($this->app->basePath('theme/views/default-wins.blade.php'));
    }

    #[Test]
    public function a_site_on_the_package_itself_takes_it_whole(): void
    {
        $before = ThemeLook::of($this->app->make(ThemeChain::class));

        $this->artisan('webx:theme:eject', ['--path' => 'site-theme'])
            ->expectsOutputToContain('The same look')
            ->assertSuccessful();

        $theme = $this->app->basePath('site-theme');
        $this->assertSame([], ThemeLook::differences($before, ThemeLook::of(ThemeChain::resolve('site-theme', $this->app->make(ThemeLocator::class)))));

        $manifest = json_decode((string) $this->files->get($theme.'/theme.json'), true)['theme'];
        $this->assertSame('Fixture', $manifest['title']);
        $this->assertFileExists($theme.'/tokens.json');
        $this->assertFileExists($theme.'/src/css/theme.css');
        $this->assertFileExists($theme.'/tests/ThemeTest.php');
        $this->assertFileDoesNotExist($theme.'/composer.json');
        $this->assertSame("WEBX_THEME=site-theme\n", $this->files->get($this->app->basePath('.env')));
    }
}
