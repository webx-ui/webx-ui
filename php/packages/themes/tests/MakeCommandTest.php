<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Testing\ThemeProbe;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeHead;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\Tokens;

/**
 * `webx:theme:make --local`: the directory `webx:setup` gives every new site (spec §14.1, §17).
 *
 * Written into the Testbench application and removed afterwards; the theme it stands on is the
 * fixture the other tests use.
 */
final class MakeCommandTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->files->deleteDirectory($this->app->basePath('made-theme'));
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->app->basePath('made-theme'));
        $this->files->delete($this->app->basePath('phpunit.xml'));

        parent::tearDown();
    }

    #[Test]
    public function it_lays_out_a_local_theme_the_engine_can_read(): void
    {
        $this->artisan('webx:theme:make', ['name' => 'made-theme', '--uses' => ['webx-ui/theme-fixture'], '--local' => true])
            ->assertSuccessful();

        $path = $this->app->basePath('made-theme');

        foreach (['theme.json', 'tokens.json', 'tests/ThemeTest.php', ...ThemeHead::LOCAL_ENTRIES] as $file) {
            $this->assertFileExists($path.'/'.$file);
        }

        // The chain it makes is the one a site with WEBX_THEME=made-theme boots: local on top,
        // the theme it named underneath, and every value from below while tokens.json is empty.
        $chain = ThemeChain::resolve('made-theme', $this->app->make(ThemeLocator::class));

        $this->assertSame(['made-theme', 'webx-ui/theme-fixture'], array_map(fn ($layer) => $layer->name, $chain->layers));
        $this->assertTrue($chain->layers[0]->local);
        $this->assertSame([], $chain->layers[0]->tokens()->defaults);

        $tokens = new Tokens($chain);
        $this->assertStringContainsString('--site-', $tokens->css());
    }

    #[Test]
    public function the_test_it_writes_is_php_the_site_can_load(): void
    {
        $this->artisan('webx:theme:make', ['name' => 'made-theme', '--local' => true])->assertSuccessful();

        $source = (string) $this->files->get($this->app->basePath('made-theme/tests/ThemeTest.php'));

        $this->assertStringStartsWith('<?php', $source);
        $this->assertStringContainsString('namespace Tests\Theme;', $source);
        $this->assertStringContainsString('class ThemeTest extends TestCase', $source);
    }

    #[Test]
    public function it_adds_the_theme_tests_to_phpunit_once(): void
    {
        $this->files->put($this->app->basePath('phpunit.xml'), <<<'XML'
            <phpunit>
                <testsuites>
                    <testsuite name="Unit">
                        <directory>tests/Unit</directory>
                    </testsuite>
                </testsuites>
            </phpunit>
            XML);

        $this->artisan('webx:theme:make', ['name' => 'made-theme', '--local' => true])->assertSuccessful();

        $xml = (string) $this->files->get($this->app->basePath('phpunit.xml'));

        $this->assertSame(1, substr_count($xml, '<directory>made-theme/tests</directory>'));
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString("        <testsuite name=\"Theme\">\n            <directory>made-theme/tests</directory>", $xml);
    }

    #[Test]
    public function it_refuses_a_directory_that_already_has_something_in_it(): void
    {
        $this->files->ensureDirectoryExists($this->app->basePath('made-theme'));
        $this->files->put($this->app->basePath('made-theme/notes.md'), 'mine');

        $this->artisan('webx:theme:make', ['name' => 'made-theme', '--local' => true])->assertFailed();

        $this->assertFileDoesNotExist($this->app->basePath('made-theme/theme.json'));
    }

    #[Test]
    public function it_refuses_a_theme_to_stand_on_that_nobody_can_find(): void
    {
        $this->artisan('webx:theme:make', ['name' => 'made-theme', '--uses' => ['webx-ui/theme-typo'], '--local' => true])
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->app->basePath('made-theme'));
    }

    #[Test]
    public function a_packaged_theme_is_named_like_a_composer_package(): void
    {
        $this->artisan('webx:theme:make', ['name' => 'made-theme'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->app->basePath('made-theme'));
        $this->assertDirectoryDoesNotExist($this->app->basePath('packages/made-theme'));
    }

    /**
     * A packaged theme (§17) over theme-default — the default when nothing is named — green by
     * the contract its own test runs, before anyone has built it: an agent starts from a theme
     * that works, and the first red line is the first rule it broke.
     */
    #[Test]
    public function a_packaged_theme_keeps_the_contract_from_the_first_run(): void
    {
        $this->artisan('webx:theme:make', ['name' => 'acme/theme-made', '--path' => 'made-theme'])
            ->expectsOutputToContain('standing on webx-ui/theme-default')
            ->assertSuccessful();

        $path = $this->app->basePath('made-theme');

        foreach (['composer.json', 'package.json', 'vite.config.js', 'phpunit.xml.dist', '.gitignore', 'README.md',
            '.github/workflows/theme.yml', 'tokens.json', 'src/css/theme.css', 'dist/theme.css', 'dist/sources.json'] as $file) {
            $this->assertFileExists($path.'/'.$file);
        }

        $this->assertFileDoesNotExist($path.'/theme.json', 'a packaged theme keeps its manifest in composer.json');

        $composer = json_decode((string) $this->files->get($path.'/composer.json'), true);
        $this->assertSame('webx-theme', $composer['type']);
        $this->assertSame(['title' => 'Theme Made', 'uses' => ['webx-ui/theme-default']], $composer['extra']['webx']['theme']);
        $this->assertArrayHasKey('webx-ui/theme-default', $composer['require']);
        $this->assertArrayHasKey('webx-ui/themes', $composer['require']);
        $this->assertSame(['Acme\\ThemeMade\\Tests\\' => 'tests/'], $composer['autoload-dev']['psr-4']);

        $test = (string) $this->files->get($path.'/tests/ThemeTest.php');
        $this->assertStringContainsString('namespace Acme\\ThemeMade\\Tests;', $test);
        $this->assertStringContainsString('use ThemeContract;', $test);
        $this->assertNotEmpty(token_get_all($test, TOKEN_PARSE));

        $probe = new ThemeProbe($path, $this->app);

        $this->assertSame('acme/theme-made', $probe->theme()->name);
        $this->assertSame(['acme/theme-made', 'webx-ui/theme-default'], array_map(fn ($layer) => $layer->name, $probe->chain()->layers));
        $this->assertSame([], $probe->unvalued());
        $this->assertSame([], $probe->findings());
        $this->assertSame([], $probe->unreadable());
        $this->assertSame([], $probe->layoutProblems());
        $this->assertSame([], $probe->uncompiled());
        $this->assertNull($probe->staleDist());
    }

    /** The committed dist/ is held to src/: an edit without a build is a red test, not yesterday's CSS on a site. */
    #[Test]
    public function an_edit_of_the_sources_without_a_build_turns_the_contract_red(): void
    {
        $this->artisan('webx:theme:make', ['name' => 'acme/theme-made', '--path' => 'made-theme'])->assertSuccessful();
        $this->files->append($this->app->basePath('made-theme/src/css/theme.css'), "\n:where(.site-main) { gap: var(--site-space-4); }\n");

        $this->assertStringContainsString('src/ changed since dist/ was built', (string) (new ThemeProbe($this->app->basePath('made-theme'), $this->app))->staleDist());
    }
}
