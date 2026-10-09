<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Setup\Processes;
use WebxUi\Admin\Setup\SetupFailed;
use WebxUi\Admin\Setup\ThemeChoice;

/**
 * What `webx:setup` does about the site's look (WEBX_UI_THEMES.md §14.1): a new site gets
 * `theme/` over `theme-default`, `--no-theme` gets the layout the skeleton used to ship, and a
 * site with a layout or a theme of its own keeps it.
 *
 * The whole command runs, on sqlite and with every child process stood in for — the order of
 * those processes and what lands in `.env` and `resources/views` is what is under test.
 * Creating the theme for real is `webx:theme:make`'s own test, and a site built end to end is
 * the starter site's.
 */
final class SetupThemeTest extends TestCase
{
    private Filesystem $files;

    private string $installed;

    /** @var list<string> */
    private array $ran = [];

    /** @var list<string> Paths in the Testbench application this test wrote and takes away again. */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->installed = $this->app->basePath('setup-theme-installed.json');

        $this->files->put($this->installed, (string) json_encode(['packages' => [
            ['name' => 'webx-ui/module-admin', 'version' => '0.28.0'],
            ['name' => 'webx-ui/module-auth', 'version' => '0.28.0'],
            ['name' => 'webx-ui/module-pages', 'version' => '0.28.0'],
        ]]));

        $this->app->instance(Catalogue::class, new Catalogue($this->files, $this->installed));
        $this->app->instance(Processes::class, $this->processes());

        $this->written = [
            $this->installed,
            $this->app->basePath('.env'),
            $this->app->databasePath('database.sqlite'),
            ...array_map(fn (string $file): string => $this->app->resourcePath($file), self::OWN_LAYOUT),
        ];

        $this->files->put($this->app->basePath('.env'), "APP_NAME=Laravel\nDB_CONNECTION=sqlite\n");
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            $this->files->delete($path);
        }

        parent::tearDown();
    }

    private const array OWN_LAYOUT = [
        'views/components/layout.blade.php',
        'views/components/header.blade.php',
        'views/components/footer.blade.php',
        'css/app.css',
        'js/app.js',
    ];

    // -- The decision ---------------------------------------------------------------------------

    #[Test]
    public function a_new_site_stands_on_the_default_theme(): void
    {
        $choice = ThemeChoice::decide(null, false, null, false);

        $this->assertSame('webx-ui/theme-default', $choice->package);
        $this->assertTrue($choice->themed);
        $this->assertSame('webx-ui/theme-default:^0.28.0', $choice->requirement('^0.28.0'));
    }

    #[Test]
    public function another_theme_is_required_at_its_own_version(): void
    {
        $choice = ThemeChoice::decide('acme/theme-harbour', false, null, false);

        $this->assertSame('acme/theme-harbour', $choice->requirement('^0.28.0'));
    }

    #[Test]
    public function a_theme_or_a_layout_the_site_has_is_kept(): void
    {
        $configured = ThemeChoice::decide('acme/theme-harbour', false, 'theme', false);
        $this->assertNull($configured->package);
        $this->assertTrue($configured->themed);

        $ownLayout = ThemeChoice::decide(null, false, null, true);
        $this->assertNull($ownLayout->package);
        $this->assertFalse($ownLayout->themed);

        // Asked for by name, a theme comes even over a layout of the site's own.
        $this->assertSame('acme/theme-harbour', ThemeChoice::decide('acme/theme-harbour', false, null, true)->package);

        $this->assertFalse(ThemeChoice::decide('acme/theme-harbour', true, 'theme', false)->themed);
    }

    #[Test]
    public function a_theme_that_is_not_a_package_name_stops_the_run_before_anything_is_installed(): void
    {
        $this->expectException(SetupFailed::class);

        ThemeChoice::decide('theme default', false, null, false);
    }

    // -- The run --------------------------------------------------------------------------------

    #[Test]
    public function setup_creates_the_local_theme_before_the_panel_is_wired(): void
    {
        $this->runSetup()->assertSuccessful();

        $this->assertTrue($this->required('webx-ui/theme-default:^0.28.0'));
        $make = array_search('artisan webx:theme:make theme --uses=webx-ui/theme-default --local', $this->ran, true);
        $sync = array_search('artisan webx:theme:sync', $this->ran, true);
        $panel = array_search('artisan webx:panel --sync', $this->ran, true);

        $this->assertIsInt($make);
        $this->assertIsInt($sync);
        $this->assertIsInt($panel);
        // The panel points the modules at <x-layout> only when it finds one, and with a theme
        // the layout is the theme's: the theme has to exist and be named first.
        $this->assertLessThan($panel, $make);
        $this->assertLessThan($panel, $sync);

        $this->assertStringContainsString("WEBX_THEME=theme\n", (string) $this->files->get($this->app->basePath('.env')));
        $this->assertFileDoesNotExist($this->app->resourcePath('views/components/layout.blade.php'));
    }

    #[Test]
    public function another_theme_is_installed_and_stood_on(): void
    {
        $this->runSetup(['--theme' => 'acme/theme-harbour'])->assertSuccessful();

        $this->assertTrue($this->required('acme/theme-harbour'));
        $this->assertContains('artisan webx:theme:make theme --uses=acme/theme-harbour --local', $this->ran);
    }

    #[Test]
    public function no_theme_writes_the_layout_the_skeleton_used_to_ship(): void
    {
        $this->runSetup(['--no-theme' => true])->assertSuccessful();

        $this->assertEmpty(array_filter($this->ran, fn (string $line): bool => str_contains($line, 'theme')));
        $this->assertStringNotContainsString('WEBX_THEME', (string) $this->files->get($this->app->basePath('.env')));

        foreach (self::OWN_LAYOUT as $file) {
            $this->assertFileExists($this->app->resourcePath($file));
        }

        $layout = (string) $this->files->get($this->app->resourcePath('views/components/layout.blade.php'));
        $this->assertStringContainsString("@stack('head')", $layout);
        $this->assertStringContainsString('<x-webx-blocks::region name="header" fallback="components.header" />', $layout);
    }

    #[Test]
    public function a_site_with_a_layout_of_its_own_is_not_given_a_theme(): void
    {
        $this->files->ensureDirectoryExists($this->app->resourcePath('views/components'));
        $this->files->put($this->app->resourcePath('views/components/layout.blade.php'), '<html>{{ $slot }}</html>');

        $this->runSetup()->assertSuccessful();

        $this->assertEmpty(array_filter($this->ran, fn (string $line): bool => str_contains($line, 'theme')));
        $this->assertSame('<html>{{ $slot }}</html>', $this->files->get($this->app->resourcePath('views/components/layout.blade.php')));
        $this->assertFileDoesNotExist($this->app->resourcePath('css/app.css'));
    }

    #[Test]
    public function running_it_again_keeps_the_theme_and_syncs_its_files(): void
    {
        $this->files->append($this->app->basePath('.env'), "WEBX_THEME=theme\n");

        $this->runSetup()->assertSuccessful();

        $this->assertFalse($this->required('webx-ui/theme-default:^0.28.0'));
        $this->assertEmpty(array_filter($this->ran, fn (string $line): bool => str_contains($line, 'webx:theme:make')));
        $this->assertContains('artisan webx:theme:sync', $this->ran);
    }

    /** @param  array<string, mixed>  $options */
    private function runSetup(array $options = []): PendingCommand
    {
        $command = $this->artisan('webx:setup', [
            '--modules' => 'pages,admins',
            '--db-connection' => 'sqlite',
            '--admin' => 'admin@example.test',
            '--no-demo' => true,
            '--no-build' => true,
            '--no-interaction' => true,
            ...$options,
        ]);

        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    private function processes(): Processes
    {
        $test = $this;

        return new class(new Composer($this->files), $test) extends Processes
        {
            public function __construct(Composer $composer, private readonly SetupThemeTest $test)
            {
                parent::__construct($composer);
            }

            public function run(array $command, string $cwd, array $env = [], ?callable $output = null): int
            {
                return $this->test->ran(implode(' ', $command));
            }

            public function artisan(string $base): array
            {
                return ['artisan'];
            }

            public function composer(string $base, ?string $named = null): array
            {
                return ['composer'];
            }

            public function npm(): array
            {
                return ['npm'];
            }
        };
    }

    /** Whether a `composer require` asked for the package, at exactly this constraint. */
    private function required(string $package): bool
    {
        foreach ($this->ran as $line) {
            if (str_starts_with($line, 'composer require ') && in_array($package, explode(' ', $line), true)) {
                return true;
            }
        }

        return false;
    }

    public function ran(string $command): int
    {
        $this->ran[] = $command;

        return 0;
    }
}
