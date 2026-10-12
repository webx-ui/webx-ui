<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Setup\Processes;

/**
 * `webx:modules` and `webx:module:add`, and the catalogue both of them read.
 *
 * The processes are stood in for: what the install command is worth is the order it runs things
 * in and that it stops at the first one that fails. Installing for real is what
 * `scripts/php-smoke.sh` does.
 */
final class ModulesCommandTest extends TestCase
{
    private Filesystem $files;

    private string $installed;

    /** @var list<string> */
    private array $ran = [];

    /** @var array<string, int> What the first command starting with the key exits with. */
    private array $exits = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->installed = $this->app->basePath('modules-installed.json');

        $this->install([
            ['name' => 'webx-ui/module-admin', 'version' => '0.28.0'],
            ['name' => 'webx-ui/module-auth', 'version' => '0.28.0'],
            ['name' => 'webx-ui/module-pages', 'version' => '0.28.0'],
            [
                'name' => 'acme/module-widgets',
                'version' => '1.2.0',
                'extra' => ['webx' => [
                    'module' => 'widgets',
                    'npm' => ['@acme/module-widgets' => '^1.2.0'],
                    'panel' => ['import' => "import { widgets } from '@acme/module-widgets'", 'register' => 'widgets()'],
                ]],
            ],
        ]);

        $this->app->instance(Catalogue::class, new Catalogue($this->files, $this->installed));
        $this->app->instance(PackageRegistry::class, new PackageRegistry($this->files, $this->installed));
        $this->app->instance(Processes::class, $this->processes());
    }

    protected function tearDown(): void
    {
        $this->files->delete($this->installed);

        parent::tearDown();
    }

    /** @param  list<array<string, mixed>>  $packages */
    private function install(array $packages): void
    {
        $this->files->put($this->installed, (string) json_encode(['packages' => $packages]));
    }

    private function processes(): Processes
    {
        $test = $this;

        return new class(new Composer($this->files), $test) extends Processes
        {
            public function __construct(Composer $composer, private readonly ModulesCommandTest $test)
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

    /** What the stand-in does with a command: write it down, and play Composer's part on disk. */
    public function ran(string $command): int
    {
        $this->ran[] = $command;

        foreach ($this->exits as $prefix => $status) {
            if (str_starts_with($command, $prefix)) {
                return $status;
            }
        }

        if (preg_match('{^composer require ([^: ]+)}', $command, $match) === 1) {
            $decoded = json_decode((string) $this->files->get($this->installed), true);
            $packages = is_array($decoded) && is_array($decoded['packages'] ?? null) ? $decoded['packages'] : [];
            $packages[] = ['name' => $match[1], 'version' => '0.28.0'];
            $this->install(array_values($packages));
        }

        return 0;
    }

    // -- The catalogue ----------------------------------------------------------------------

    #[Test]
    public function the_catalogue_says_what_the_packages_of_the_monorepo_say(): void
    {
        $catalogue = new Catalogue($this->files, $this->installed);
        $root = dirname(__DIR__, 2);

        foreach ($catalogue->describe() as $module) {
            $path = $root.'/'.substr($module['package'], strlen('webx-ui/')).'/composer.json';
            $this->assertFileExists($path, "{$module['id']}: no package at {$path}");

            /** @var array{require: array<string, string>, extra: array{webx: array{npm: array<string, string>}}} $composer */
            $composer = json_decode((string) $this->files->get($path), true);

            $this->assertArrayHasKey(
                $module['npm'],
                $composer['extra']['webx']['npm'],
                "{$module['id']}: the npm half is not the one extra.webx names",
            );

            $required = array_values(array_filter(array_map(
                $catalogue->idFor(...),
                array_keys($composer['require']),
            )));
            sort($required);
            $requires = $module['requires'];
            sort($requires);

            $this->assertSame($required, $requires, "{$module['id']}: requires is not what composer.json requires");
        }
    }

    #[Test]
    public function the_languages_of_the_demo_are_the_ones_its_fixtures_are_written_in(): void
    {
        $catalogue = new Catalogue($this->files, $this->installed);
        $root = dirname(__DIR__, 2);

        foreach ($catalogue->ids() as $id) {
            $package = $root.'/'.substr($catalogue->packageFor($id), strlen('webx-ui/'));
            $written = $this->languagesIn($package.'/resources/demo');
            $declared = $catalogue->demoOf($id);

            if (! is_dir($package.'/src/Demo')) {
                $this->assertSame([], $declared, "{$id}: seeds no demo, so it speaks no language");
            } elseif ($written === []) {
                // Plain strings, or no words at all: which one is not something a file can say.
                $this->assertContains($declared, [[], ['en']], "{$id}: a demo without language maps is English or wordless");
            } else {
                $this->assertSame($written, $declared, "{$id}: demo is not what resources/demo is written in");
            }
        }

        foreach ($catalogue->themeDemos() as $theme => $demo) {
            $path = $root.'/'.substr($theme, strlen('webx-ui/')).'/demo';
            $modules = array_values(array_filter(
                array_map(basename(...), $this->files->directories($path)),
                $catalogue->knows(...),
            ));
            sort($modules);

            $this->assertSame($modules, $demo['modules'], "{$theme}: not the modules it has a demo/<module>/ for");
            $this->assertSame($this->languagesIn($path), $demo['demo'], "{$theme}: demo is not what demo/ is written in");
        }
    }

    /**
     * Every language a map of languages names anywhere in the JSON under a directory — a map
     * being an object with `en` whose keys are all language codes and whose values all strings.
     *
     * @return list<string>
     */
    private function languagesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $found = [];
        $walk = function (mixed $value) use (&$walk, &$found): void {
            if (! is_array($value)) {
                return;
            }

            $isMap = array_key_exists('en', $value);

            foreach ($value as $key => $one) {
                $isMap = $isMap && is_string($key) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $key) === 1 && is_string($one);
            }

            if ($isMap) {
                $found += array_fill_keys(array_keys($value), true);

                return;
            }

            array_map($walk, $value);
        };

        foreach ($this->files->allFiles($directory) as $file) {
            if ($file->getExtension() === 'json') {
                $walk(json_decode((string) $file->getContents(), true));
            }
        }

        $languages = array_map(strval(...), array_keys($found));
        sort($languages);

        return $languages;
    }

    #[Test]
    public function modules_after_a_plus_come_on_top_of_the_suggested_ones(): void
    {
        $catalogue = new Catalogue($this->files, $this->installed);

        $added = $catalogue->chosen('+blog, services');
        foreach ([...$catalogue->suggested(), 'blog', 'services'] as $id) {
            $this->assertContains($id, $added);
        }
        $this->assertNotContains('faq', $added);

        // Without the plus, a list is the list: closed over requirements, and what is installed stays.
        $listed = $catalogue->chosen('services');
        $this->assertContains('services', $listed);
        $this->assertContains('seo', $listed, 'services requires seo');
        $this->assertContains('pages', $listed, 'installed');
        $this->assertNotContains('inbox', $listed);

        $this->assertSame($catalogue->withRequirements($catalogue->ids()), array_slice($catalogue->chosen('all'), 0, count($catalogue->ids())));

        $this->expectExceptionMessage('There is no module called [shop]');
        $catalogue->chosen('+shop');
    }

    #[Test]
    public function every_package_that_wires_the_panel_is_in_the_catalogue(): void
    {
        // The registration that is easiest to forget: without it `webx:setup` never offers the
        // module and `webx:modules` never lists it.
        $catalogue = new Catalogue($this->files, $this->installed);

        foreach ($this->files->glob(dirname(__DIR__, 2).'/*/composer.json') as $path) {
            $composer = json_decode((string) $this->files->get($path), true);

            if (! is_array($composer) || ! isset($composer['extra']['webx']['panel'])) {
                continue;
            }

            $this->assertNotNull($catalogue->idFor($composer['name']), "{$composer['name']} is not in Setup\\Catalogue");
        }
    }

    #[Test]
    public function the_requirements_come_along_however_deep(): void
    {
        $closed = (new Catalogue($this->files, $this->installed))->withRequirements(['catalog-brands']);

        sort($closed);

        $this->assertSame(['admins', 'catalog', 'catalog-brands', 'media', 'seo', 'settings'], $closed);
    }

    // -- webx:modules -----------------------------------------------------------------------

    #[Test]
    public function it_lists_the_catalogue_as_json_with_what_is_installed(): void
    {
        $this->assertSame(0, Artisan::call('webx:modules', ['--json' => true]));

        $json = json_decode(Artisan::output(), true);
        $this->assertIsArray($json);

        /** @var list<array<string, mixed>> $modules */
        $modules = $json['modules'];
        $byId = array_column($modules, null, 'id');

        $this->assertSame([
            'id' => 'catalog-brands',
            'package' => 'webx-ui/module-catalog-brands',
            'npm' => '@webx-ui/module-catalog-brands',
            'label' => $byId['catalog-brands']['label'],
            'default' => false,
            'requires' => ['catalog', 'media', 'seo'],
            'installed' => false,
        ], $byId['catalog-brands']);

        $this->assertTrue($byId['admins']['installed']);
        $this->assertSame('webx-ui/module-auth', $byId['admins']['package']);
        $this->assertTrue($byId['pages']['installed']);
        $this->assertFalse($byId['blog']['installed']);

        // A module from outside the catalogue, installed, by what its own package says.
        $this->assertSame('acme/module-widgets', $byId['widgets']['package']);
        $this->assertSame('@acme/module-widgets', $byId['widgets']['npm']);
        $this->assertTrue($byId['widgets']['installed']);
    }

    #[Test]
    public function without_json_it_is_a_table(): void
    {
        $this->assertSame(0, Artisan::call('webx:modules'));

        $this->assertMatchesRegularExpression(
            '/catalog-brands\s*\|\s*webx-ui\/module-catalog-brands\s*\|\s*\|\s*catalog, media, seo\s*\|/',
            Artisan::output(),
        );
    }

    // -- webx:module:add --------------------------------------------------------------------

    #[Test]
    public function it_installs_by_id_and_runs_the_rest_in_order(): void
    {
        $this->artisan('webx:module:add', ['package' => 'faq'])->assertSuccessful();

        $this->assertSame([
            'composer require webx-ui/module-faq:^0.28.0 --no-interaction --no-progress',
            'artisan webx:panel --sync',
            'npm install',
            'npm run build',
        ], $this->ran);
    }

    #[Test]
    public function a_third_party_package_is_asked_for_as_given(): void
    {
        $this->artisan('webx:module:add', ['package' => 'acme/module-gallery', '--no-build' => true])->assertSuccessful();

        $this->assertSame([
            'composer require acme/module-gallery --no-interaction --no-progress',
            'artisan webx:panel --sync',
            'npm install',
        ], $this->ran);
    }

    #[Test]
    public function a_constraint_given_is_the_constraint_used(): void
    {
        $this->artisan('webx:module:add', ['package' => 'acme/module-gallery:^2.0', '--no-build' => true])->assertSuccessful();

        $this->assertSame('composer require acme/module-gallery:^2.0 --no-interaction --no-progress', $this->ran[0]);
    }

    #[Test]
    public function an_installed_package_is_not_an_error_and_still_gets_the_rest(): void
    {
        $this->artisan('webx:module:add', ['package' => 'webx-ui/module-pages'])
            ->expectsOutputToContain('already installed')
            ->assertSuccessful();

        $this->assertSame(['artisan webx:panel --sync', 'npm install', 'npm run build'], $this->ran);
    }

    #[Test]
    public function a_failed_composer_stops_everything_with_a_failure(): void
    {
        $this->exits = ['composer require' => 2];

        $this->artisan('webx:module:add', ['package' => 'faq'])->assertFailed();

        $this->assertCount(1, $this->ran);
    }

    #[Test]
    public function a_failed_npm_install_is_a_failure_too(): void
    {
        $this->exits = ['npm install' => 1];

        $this->artisan('webx:module:add', ['package' => 'faq'])->assertFailed();

        $this->assertSame('npm install', end($this->ran));
    }

    #[Test]
    public function a_composer_that_installed_nothing_is_a_failure(): void
    {
        // Exit code 0 and nothing in vendor: Composer was told something it read differently.
        $this->exits = ['composer require' => 0];

        $this->artisan('webx:module:add', ['package' => 'faq'])
            ->expectsOutputToContain('still not in vendor/composer/installed.json')
            ->assertFailed();
    }

    #[Test]
    public function something_that_is_not_a_package_name_is_refused_before_composer(): void
    {
        $this->artisan('webx:module:add', ['package' => '--dev'])->assertFailed();
        $this->artisan('webx:module:add', ['package' => 'widgets'])->assertFailed();

        $this->assertSame([], $this->ran);
    }
}
