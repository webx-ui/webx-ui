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
 * `webx:theme:extract` (spec §14.3): the site's local theme becomes a package the site then
 * stands on, and the site looks the same.
 *
 * The local theme is a copy of the fixture site's, put where a site keeps it — `theme/` under
 * the project — before the application boots, because the chain is resolved at boot.
 */
final class ExtractCommandTest extends TestCase
{
    use SiteSandbox;

    private Filesystem $files;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $this->sandbox($app->basePath(), ['theme', 'harbor-package']);
        (new Filesystem)->copyDirectory(self::fixture('site/theme'), $app->basePath('theme'));

        parent::defineEnvironment($app);
    }

    protected function theme(): string
    {
        return 'theme';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->files->put($this->app->basePath('.env'), "APP_NAME=Harbor\nWEBX_THEME=theme\n");
        $this->files->put($this->app->basePath('phpunit.xml'), <<<'XML'
            <phpunit>
                <testsuites>
                    <testsuite name="Unit">
                        <directory>tests/Unit</directory>
                    </testsuite>
                    <testsuite name="Theme">
                        <directory>theme/tests</directory>
                    </testsuite>
                </testsuites>
            </phpunit>
            XML);
        $this->files->put($this->app->basePath('composer.json'), "{\n    \"name\": \"harbor/site\",\n    \"require\": {\n        \"php\": \"^8.4\"\n    },\n    \"require-dev\": {}\n}\n");
        $this->files->ensureDirectoryExists($this->app->basePath('theme/tests'));
        $this->files->copy(dirname(__DIR__).'/stubs/local/tests/ThemeTest.php.stub', $this->app->basePath('theme/tests/ThemeTest.php'));
    }

    protected function tearDown(): void
    {
        $this->restoreSandbox();

        parent::tearDown();
    }

    #[Test]
    public function the_local_theme_becomes_a_package_with_the_same_look(): void
    {
        $before = ThemeLook::of($this->app->make(ThemeChain::class));

        $this->artisan('webx:theme:extract', ['name' => 'harbor/theme-harbor', '--path' => 'harbor-package'])
            ->expectsOutputToContain('The same look')
            ->expectsOutputToContain('composer update harbor/theme-harbor')
            ->assertSuccessful();

        $package = $this->app->basePath('harbor-package');

        $this->assertDirectoryDoesNotExist($this->app->basePath('theme'));
        $this->assertFileDoesNotExist($package.'/theme.json');

        foreach (['tokens.json', 'views/local-wins.blade.php', 'blocks/hero.json', 'icons/phone.svg', 'assets/img/wave.svg', 'lang/en/theme.php',
            'src/css/theme.css', 'package.json', 'vite.config.js', 'phpunit.xml.dist', '.github/workflows/theme.yml', 'tests/ThemeTest.php'] as $file) {
            $this->assertFileExists($package.'/'.$file);
        }

        $composer = json_decode((string) $this->files->get($package.'/composer.json'), true);
        $this->assertSame('harbor/theme-harbor', $composer['name']);
        $this->assertSame('webx-theme', $composer['type']);
        $this->assertSame(['title' => 'Local', 'uses' => ['webx-ui/theme-fixture'], 'requires' => ['module-pages']], $composer['extra']['webx']['theme']);
        $this->assertStringContainsString('namespace Harbor\\ThemeHarbor\\Tests;', (string) $this->files->get($package.'/tests/ThemeTest.php'));

        // The package's look is the local theme's: told to the locator as Composer will install it.
        $locator = $this->app->make(ThemeLocator::class);
        $locator->register('harbor/theme-harbor', $package);
        $this->assertSame([], ThemeLook::differences($before, ThemeLook::of(ThemeChain::resolve('harbor/theme-harbor', $locator))));
    }

    #[Test]
    public function the_site_stands_on_the_package(): void
    {
        $this->artisan('webx:theme:extract', ['name' => 'harbor/theme-harbor', '--path' => 'harbor-package'])->assertSuccessful();

        $composer = json_decode((string) $this->files->get($this->app->basePath('composer.json')), true);
        $this->assertSame([['type' => 'path', 'url' => 'harbor-package', 'options' => ['symlink' => true]]], $composer['repositories']);
        $this->assertSame('@dev', $composer['require']['harbor/theme-harbor']);
        $this->assertStringContainsString('"require-dev": {}', (string) $this->files->get($this->app->basePath('composer.json')), 'an empty object stays an object');

        $this->assertSame("APP_NAME=Harbor\nWEBX_THEME=harbor/theme-harbor\n", $this->files->get($this->app->basePath('.env')));

        $xml = (string) $this->files->get($this->app->basePath('phpunit.xml'));
        $this->assertStringNotContainsString('theme/tests', $xml);
        $this->assertStringContainsString('<directory>tests/Unit</directory>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    /** The skeleton's Vite config drops an entry that is not there, so only another config is told to remove them. */
    #[Test]
    public function the_vite_entries_are_named_only_where_the_config_does_not_skip_a_missing_one(): void
    {
        $config = $this->app->basePath('vite.config.js');

        try {
            $this->files->put($config, "laravel({ input: ['theme/src/css/theme.css', 'resources/js/app.js'] })\n");
            $this->artisan('webx:theme:extract', ['name' => 'harbor/theme-harbor', '--path' => 'harbor-package'])
                ->expectsOutputToContain("remove 'theme/src/css/theme.css'")
                ->assertSuccessful();
        } finally {
            $this->files->delete($config);
        }
    }

    #[Test]
    public function the_skeletons_vite_config_needs_no_word(): void
    {
        $config = $this->app->basePath('vite.config.js');

        try {
            $this->files->put($config, "laravel({ input: ['theme/src/css/theme.css'].filter((entry) => existsSync(entry)) })\n");
            $this->artisan('webx:theme:extract', ['name' => 'harbor/theme-harbor', '--path' => 'harbor-package'])
                ->doesntExpectOutputToContain("from the laravel() plugin's input")
                ->assertSuccessful();
        } finally {
            $this->files->delete($config);
        }
    }

    #[Test]
    public function it_refuses_a_directory_that_already_has_something_in_it(): void
    {
        $this->files->ensureDirectoryExists($this->app->basePath('harbor-package'));
        $this->files->put($this->app->basePath('harbor-package/notes.md'), 'mine');

        $this->artisan('webx:theme:extract', ['name' => 'harbor/theme-harbor', '--path' => 'harbor-package'])->assertFailed();

        $this->assertFileExists($this->app->basePath('theme/theme.json'), 'nothing moved');
    }

    #[Test]
    public function it_refuses_a_name_composer_would_not_take(): void
    {
        $this->artisan('webx:theme:extract', ['name' => 'Harbor Theme'])->assertFailed();

        $this->assertFileExists($this->app->basePath('theme/theme.json'));
    }
}
