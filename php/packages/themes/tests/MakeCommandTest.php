<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ConfigAppearance;
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

        $tokens = new Tokens($chain, new ConfigAppearance($this->app->make('config')));
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
    public function a_packaged_theme_is_not_scaffolded_yet(): void
    {
        $this->artisan('webx:theme:make', ['name' => 'made-theme'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->app->basePath('made-theme'));
    }
}
