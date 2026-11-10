<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Process\Process;
use WebxUi\Admin\Doctor\Checks\SiteModules;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Admin\Setup\Processes;

/**
 * `webx:make-module --local` (SITE_MODULES §9) on a site of its own in a temporary folder: an
 * older site, without the path repository, the suite `Modules` or the Vue plugin.
 *
 * There is no module committed anywhere to compare with (§16, decision 10): this test is the
 * example. It writes the module, checks the wiring and that a second run changes nothing, and
 * runs the tests the module was generated with on a Testbench site of the monorepo's packages.
 */
final class MakeLocalModuleCommandTest extends TestCase
{
    private Filesystem $files;

    private string $site = '';

    private string $base = '';

    /** @var list<string> */
    private array $ran = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->site = sys_get_temp_dir().'/webx-local-module-'.bin2hex(random_bytes(4));

        foreach ($this->files->files(__DIR__.'/Fixtures/local-module-site') as $file) {
            $this->files->ensureDirectoryExists($this->site);
            $this->files->copy($file->getPathname(), $this->site.'/'.$file->getBasename('.fixture'));
        }

        $this->installed([]);

        $this->base = $this->app->basePath();
        $this->app->setBasePath($this->site);

        $installed = $this->site.'/vendor/composer/installed.json';
        $this->app->instance(Catalogue::class, new Catalogue($this->files, $installed));
        $this->app->instance(PackageRegistry::class, new PackageRegistry($this->files, $installed));
        $this->app->instance(Processes::class, $this->processes());
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->base);
        $this->files->deleteDirectory($this->site);

        parent::tearDown();
    }

    #[Test]
    public function it_writes_both_halves_of_the_module(): void
    {
        $this->make('offices')->assertSuccessful();

        $module = $this->site.'/modules/offices';

        foreach ([
            'composer.json', 'AGENTS.md', 'README.md', 'config/webx-offices.php', 'routes/api.php',
            'src/OfficesServiceProvider.php', 'src/Panel/OfficesModule.php', 'src/Panel/OfficeForm.php',
            'src/Panel/OfficeList.php', 'src/Models/Office.php', 'src/Http/Controllers/OfficeController.php',
            'src/Http/Resources/OfficeResource.php', 'src/Mcp/OfficesTools.php', 'src/Mcp/OfficesResources.php',
            'src/Demo/OfficesDemo.php', 'resources/screens/form.json', 'resources/demo/offices.json',
            'tests/TestCase.php', 'tests/AccessCoverageTest.php', 'tests/HelpCoverageTest.php',
            'tests/McpDryRunTest.php', 'tests/TranslationsTest.php', 'tests/PerformanceTest.php', 'tests/PanelTest.php',
            'js/package.json', 'js/tsconfig.json', 'js/src/index.ts', 'js/src/module.ts', 'js/src/api.ts',
            'js/src/i18n.ts', 'js/src/messages.ts', 'js/src/messages.test.ts', 'js/src/types.ts',
            'js/src/OfficesPage.vue', 'js/src/OfficeFormPage.vue',
        ] as $path) {
            $this->assertFileExists("{$module}/{$path}");
        }

        // English and the languages of the panel (en, ru, uk by default), the words and the help.
        foreach (['en', 'ru', 'uk'] as $language) {
            foreach (['module', 'list', 'form', 'errors'] as $group) {
                $this->assertFileExists("{$module}/lang/{$language}/{$group}.php");
            }

            $this->assertFileExists("{$module}/resources/help/{$language}/offices.index.md");
            $this->assertFileExists("{$module}/resources/help/{$language}/offices.form.md");
        }

        $this->assertCount(1, glob($module.'/database/migrations/*_create_offices_table.php') ?: []);

        $composer = $this->decoded($module.'/composer.json');
        $this->assertSame('site/module-offices', $composer['name']);
        $this->assertSame('0.1.0', $composer['version']);
        $this->assertSame(['Site\\Offices\\' => 'src/'], $composer['autoload']['psr-4']);
        $this->assertSame(['Site\\Offices\\OfficesServiceProvider'], $composer['extra']['laravel']['providers']);
        $this->assertSame([
            'module' => 'offices',
            'npm' => ['@site/module-offices' => 'file:modules/offices/js'],
            'panel' => ['import' => "import { offices } from '@site/module-offices'", 'register' => 'offices()'],
        ], $composer['extra']['webx']);

        $npm = $this->decoded($module.'/js/package.json');
        $this->assertSame('@site/module-offices', $npm['name']);
        $this->assertTrue($npm['private']);
        $this->assertSame(['.' => './src/index.ts'], $npm['exports']);
        $this->assertArrayNotHasKey('dependencies', $npm, '@webx-ui/* and vue come from the site');

        $this->assertSame('offices.form', $this->decoded($module.'/resources/screens/form.json')['screen']);
        $this->assertCount(3, $this->decoded($module.'/resources/demo/offices.json')['rows']);

        foreach ($this->files->allFiles($module) as $file) {
            $this->assertStringNotContainsString('{{ ', (string) preg_replace('/\{\{ (item|t\(|sortable)[^}]*\}\}/', '', $file->getContents()), $file->getRelativePathname().' keeps a placeholder');

            if ($file->getExtension() === 'php') {
                exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $status);
                $this->assertSame(0, $status, implode("\n", $output));
            }
        }
    }

    #[Test]
    public function a_name_of_two_words_is_named_like_a_package_of_the_monorepo(): void
    {
        $this->make('OfficeHours')->assertSuccessful();

        $module = $this->site.'/modules/office-hours';
        $this->assertFileExists($module.'/src/OfficeHoursServiceProvider.php');
        $this->assertFileExists($module.'/src/Models/OfficeHour.php');
        $this->assertCount(1, glob($module.'/database/migrations/*_create_office_hours_table.php') ?: []);

        $composer = $this->decoded($module.'/composer.json');
        $this->assertSame('site/module-office-hours', $composer['name']);
        $this->assertSame('officeHours()', $composer['extra']['webx']['panel']['register']);
        $this->assertStringContainsString("'office_hours_'", (string) file_get_contents($module.'/src/Mcp/OfficeHoursTools.php'));
        $this->assertStringContainsString("'webx-office-hours'", (string) file_get_contents($module.'/src/OfficeHoursServiceProvider.php'));
    }

    #[Test]
    public function the_site_is_wired_once_and_a_second_run_changes_nothing(): void
    {
        $this->make('offices')
            ->expectsOutputToContain('added the path repository modules/* and the autoload of Site\\Offices\\Tests\\')
            ->expectsOutputToContain('added the suite Modules')
            ->expectsOutputToContain("add import vue from '@vitejs/plugin-vue'")
            ->expectsOutputToContain('COPY modules ./modules before npm ci')
            ->assertSuccessful();

        $composer = $this->decoded($this->site.'/composer.json');
        $this->assertSame([['type' => 'path', 'url' => 'modules/*', 'options' => ['symlink' => true]]], $composer['repositories']);
        $this->assertSame(['Tests\\' => 'tests/', 'Site\\Offices\\Tests\\' => 'modules/offices/tests/'], $composer['autoload-dev']['psr-4']);
        // Written back the way Composer writes it: an empty object stays one.
        $this->assertStringContainsString('"allow-plugins": {}', (string) file_get_contents($this->site.'/composer.json'));

        $phpunit = (string) file_get_contents($this->site.'/phpunit.xml');
        $this->assertStringContainsString(
            "        </testsuite>\n        <testsuite name=\"Modules\">\n            <directory>modules/*/tests</directory>\n        </testsuite>\n    </testsuites>",
            $phpunit,
        );
        $this->assertNotFalse(simplexml_load_string($phpunit));

        $package = (string) file_get_contents($this->site.'/package.json');
        $this->assertStringContainsString("\n    \"scripts\": {\n", $package, 'the four spaces of the file are kept');
        $decoded = $this->decoded($this->site.'/package.json');
        $this->assertSame('vue-tsc -p modules/tsconfig.json --noEmit', $decoded['scripts']['typecheck:modules']);
        $this->assertSame('vitest run --dir modules', $decoded['scripts']['test:modules']);
        $this->assertSame('^8.0.0', $decoded['devDependencies']['vite']);

        foreach (['@vitejs/plugin-vue', 'vue-tsc', 'vitest', '@vue/test-utils', 'jsdom', 'typescript', '@types/node'] as $dependency) {
            $this->assertArrayHasKey($dependency, $decoded['devDependencies']);
        }

        $this->assertFileExists($this->site.'/modules/tsconfig.json');
        $this->assertSame(
            (string) file_get_contents(__DIR__.'/Fixtures/local-module-site/vite.config.js.fixture'),
            (string) file_get_contents($this->site.'/vite.config.js'),
            'the Vite config is named, not edited',
        );

        $before = $this->snapshot();

        $this->make('offices', ['--force' => true])
            ->expectsOutputToContain('already has the path repository and the autoload of the tests')
            ->expectsOutputToContain('already runs modules/*/tests')
            ->expectsOutputToContain('already has what the modules are checked with')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    #[Test]
    public function an_id_of_a_module_of_webx_ui_is_refused(): void
    {
        $this->make('faq')
            ->expectsOutputToContain('[faq] is the id of webx-ui/module-faq')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->site.'/modules/faq');
        $this->assertSame((string) file_get_contents(__DIR__.'/Fixtures/local-module-site/composer.json.fixture'), (string) file_get_contents($this->site.'/composer.json'));
    }

    #[Test]
    public function an_id_an_installed_package_has_is_refused(): void
    {
        $this->installed([['name' => 'acme/module-places', 'version' => '1.0.0', 'extra' => ['webx' => ['module' => 'places']]]]);

        $this->make('places')
            ->expectsOutputToContain('[places] is the id of acme/module-places')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->site.'/modules/places');
    }

    #[Test]
    public function a_name_that_makes_no_id_is_refused(): void
    {
        $this->make('2offices')->assertFailed();
        $this->make('offices_list')->assertFailed();
        $this->make('list')->expectsOutputToContain('a word PHP keeps for itself')->assertFailed();

        $this->assertDirectoryDoesNotExist($this->site.'/modules');
    }

    #[Test]
    public function a_folder_that_is_there_is_written_over_only_with_force_and_only_the_scaffold(): void
    {
        $this->files->ensureDirectoryExists($this->site.'/modules/offices/src');
        $this->files->put($this->site.'/modules/offices/src/Mine.php', '<?php // the site\'s own');
        $this->files->put($this->site.'/modules/offices/README.md', 'mine');

        $this->make('offices')->expectsOutputToContain('modules/offices is already there')->assertFailed();
        $this->assertFileDoesNotExist($this->site.'/modules/offices/composer.json');

        $this->make('offices', ['--force' => true])->assertSuccessful();

        $this->assertSame('<?php // the site\'s own', file_get_contents($this->site.'/modules/offices/src/Mine.php'));
        $this->assertStringContainsString('# Offices', (string) file_get_contents($this->site.'/modules/offices/README.md'));
        $this->assertFileExists($this->site.'/modules/offices/composer.json');
    }

    #[Test]
    public function a_language_of_the_panel_the_stubs_do_not_speak_gets_the_english_to_translate(): void
    {
        config(['webx-localization.panel' => ['de']]);

        $this->make('offices')->expectsOutputToContain('written in English, to translate')->assertSuccessful();

        $module = $this->site.'/modules/offices';
        $this->assertFileEquals($module.'/lang/en/list.php', $module.'/lang/de/list.php');
        $this->assertFileEquals($module.'/resources/help/en/offices.form.md', $module.'/resources/help/de/offices.form.md');
        $this->assertDirectoryDoesNotExist($module.'/lang/ru');
    }

    #[Test]
    public function it_installs_through_a_child_module_add_and_then_migrates(): void
    {
        $this->artisan('webx:make-module', ['name' => 'offices', '--local' => true])->assertSuccessful();

        $this->assertSame(['artisan webx:module:add site/module-offices', 'artisan migrate --force'], $this->ran);
    }

    #[Test]
    public function a_step_of_the_install_that_fails_fails_the_command(): void
    {
        $this->app->instance(Processes::class, $this->processes(exit: 1));

        $this->artisan('webx:make-module', ['name' => 'offices', '--local' => true])
            ->expectsOutputToContain('php artisan webx:module:add site/module-offices failed')
            ->assertFailed();

        $this->assertSame(['artisan webx:module:add site/module-offices'], $this->ran, 'nothing after a failed step');
    }

    #[Test]
    public function the_doctor_finds_nothing_wrong_with_the_module_on_a_site_set_up_for_it(): void
    {
        $this->make('offices')->assertSuccessful();

        // What `composer require` writes, and what the skeleton's own files look like.
        $composer = $this->decoded($this->site.'/modules/offices/composer.json');
        $this->installed([['name' => 'site/module-offices', 'version' => '0.1.0', 'install-path' => '../../modules/offices', 'extra' => $composer['extra']]]);
        $this->files->put($this->site.'/vite.config.js', "import laravel from 'laravel-vite-plugin'\nimport vue from '@vitejs/plugin-vue'\n\nexport default { plugins: [laravel({ input: [] }), vue()] }\n");
        $this->files->put($this->site.'/Dockerfile', "FROM composer:2 AS vendor\nCOPY . .\nRUN composer install && mkdir -p modules\n\nFROM node:22 AS assets\nCOPY package.json ./\nCOPY --from=vendor /app/modules ./modules\nRUN npm ci\n");

        $found = $this->app->make(SiteModules::class)->run();

        $this->assertSame([Diagnosis::OK], array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->state, $found), implode("\n", array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->detail, $found)));
    }

    #[Test]
    public function the_tests_the_module_comes_with_pass_on_a_site(): void
    {
        $this->make('offices')->assertSuccessful();

        foreach ($this->files->files(__DIR__.'/Fixtures/local-module-run') as $file) {
            $name = $file->getBasename('.fixture');
            $target = $name === 'TestCase.php' ? $this->site.'/tests/TestCase.php' : $this->site.'/'.($name === 'phpunit.xml' ? 'phpunit.run.xml' : $name);
            $this->files->ensureDirectoryExists(dirname($target));
            $this->files->copy($file->getPathname(), $target);
        }

        $vendor = dirname((string) (new ReflectionClass(ClassLoader::class))->getFileName(), 2);

        $process = new Process(
            [PHP_BINARY, $vendor.'/bin/phpunit', '--configuration', $this->site.'/phpunit.run.xml'],
            $this->site,
            ['WEBX_MONOREPO_AUTOLOAD' => $vendor.'/autoload.php'],
        );
        $process->setTimeout(300);
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();

        $this->assertSame(0, $process->getExitCode(), $output);
        $this->assertMatchesRegularExpression('/OK \(\d+ tests?, \d+ assertions?\)/', $output);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function make(string $name, array $options = []): PendingCommand
    {
        $command = $this->artisan('webx:make-module', ['name' => $name, '--local' => true, '--no-install' => true, ...$options]);
        assert($command instanceof PendingCommand);

        return $command;
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     */
    private function installed(array $packages): void
    {
        $this->files->ensureDirectoryExists($this->site.'/vendor/composer');
        $this->files->put($this->site.'/vendor/composer/installed.json', (string) json_encode(['packages' => $packages]));
    }

    /**
     * Every file of the site but the stand-in for `vendor`, by its contents.
     *
     * @return array<string, string>
     */
    private function snapshot(): array
    {
        $files = [];

        foreach ($this->files->allFiles($this->site, true) as $file) {
            if (! str_starts_with($file->getRelativePathname(), 'vendor')) {
                $files[$file->getRelativePathname()] = $file->getContents();
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function decoded(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded, "{$path} is not JSON");

        return $decoded;
    }

    private function processes(int $exit = 0): Processes
    {
        $test = $this;

        return new class(new Composer($this->files), $test, $exit) extends Processes
        {
            public function __construct(Composer $composer, private readonly MakeLocalModuleCommandTest $test, private readonly int $exit)
            {
                parent::__construct($composer);
            }

            public function run(array $command, string $cwd, array $env = [], ?callable $output = null): int
            {
                $this->test->ran(implode(' ', $command));

                return $this->exit;
            }

            public function artisan(string $base): array
            {
                return ['artisan'];
            }
        };
    }

    /** What the stand-in does with a command: write it down. */
    public function ran(string $command): void
    {
        $this->ran[] = $command;
    }
}
