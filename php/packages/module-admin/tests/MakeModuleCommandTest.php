<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\Contracts\ProvidesDemo;

final class MakeModuleCommandTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = $this->app->path('Cms');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        (new Filesystem)->deleteDirectory($this->app->resourcePath('demo'));
        (new Filesystem)->deleteDirectory($this->app->basePath('tests/Feature/Cms'));

        parent::tearDown();
    }

    #[Test]
    public function it_writes_a_module_class(): void
    {
        $this->artisan('webx:make-module', ['name' => 'Pages'])->assertSuccessful();

        $path = $this->directory.'/Modules/PagesModule.php';

        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('namespace App\Cms\Modules;', $contents);
        $this->assertStringContainsString('class PagesModule extends AbstractModule', $contents);
        $this->assertStringContainsString("return 'pages';", $contents);
        $this->assertStringContainsString("'pages.view'", $contents);
    }

    #[Test]
    public function a_name_that_already_says_module_is_not_said_twice(): void
    {
        $this->artisan('webx:make-module', ['name' => 'MediaModule'])->assertSuccessful();

        $this->assertFileExists($this->directory.'/Modules/MediaModule.php');
        $this->assertFileDoesNotExist($this->directory.'/Modules/MediaModuleModule.php');
    }

    #[Test]
    public function a_multi_word_name_becomes_a_kebab_case_id(): void
    {
        $this->artisan('webx:make-module', ['name' => 'MediaLibrary'])->assertSuccessful();

        $contents = (string) file_get_contents($this->directory.'/Modules/MediaLibraryModule.php');

        $this->assertStringContainsString("return 'media-library';", $contents);
        $this->assertStringContainsString("return 'Media Library';", $contents);
    }

    #[Test]
    public function it_refuses_to_overwrite_without_being_told_to(): void
    {
        $this->artisan('webx:make-module', ['name' => 'Pages'])->assertSuccessful();
        $this->artisan('webx:make-module', ['name' => 'Pages'])->assertFailed();
        $this->artisan('webx:make-module', ['name' => 'Pages', '--force' => true])->assertSuccessful();
    }

    #[Test]
    public function the_generated_class_satisfies_the_module_contract(): void
    {
        $this->artisan('webx:make-module', ['name' => 'Pages'])->assertSuccessful();

        // Loading it is half the test: the stub has to be valid PHP. The other half is that
        // what it declares is something the registry would accept.
        require_once $this->directory.'/Modules/PagesModule.php';

        $class = 'App\Cms\Modules\PagesModule';

        $this->assertTrue(class_exists($class), 'The generated file declares no such class.');
        $this->assertTrue(is_subclass_of($class, Module::class), 'The generated class is not a module.');
        $this->assertTrue(is_subclass_of($class, ProvidesDemo::class), 'The generated class cannot seed a demo.');
    }

    #[Test]
    public function it_makes_somewhere_for_the_demo_fixtures_to_live(): void
    {
        $this->artisan('webx:make-module', ['name' => 'MediaLibrary'])->assertSuccessful();

        $this->assertFileExists($this->app->resourcePath('demo/media-library/.gitkeep'));
    }

    #[Test]
    public function it_writes_a_query_budget_test_beside_the_module(): void
    {
        $this->artisan('webx:make-module', ['name' => 'MediaLibrary'])->assertSuccessful();

        $path = $this->app->basePath('tests/Feature/Cms/MediaLibraryModulePerformanceTest.php');
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('namespace Tests\\Feature\\Cms;', $contents);
        $this->assertStringContainsString('class MediaLibraryModulePerformanceTest extends TestCase', $contents);
        $this->assertStringContainsString('use CountsQueries;', $contents);
        $this->assertStringContainsString("\$this->get('/media-library')", $contents);
        $this->assertStringNotContainsString('{{', $contents);

        // Valid PHP, though it cannot be loaded here: Tests\TestCase is the site's.
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
    }

    #[Test]
    public function it_writes_an_access_test_beside_the_module(): void
    {
        $this->artisan('webx:make-module', ['name' => 'MediaLibrary'])->assertSuccessful();

        $path = $this->app->basePath('tests/Feature/Cms/MediaLibraryModuleAccessTest.php');
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('namespace Tests\\Feature\\Cms;', $contents);
        $this->assertStringContainsString('class MediaLibraryModuleAccessTest extends TestCase', $contents);
        $this->assertStringContainsString('use AssertsPermissions;', $contents);
        $this->assertStringContainsString("config('webx-admin.api_path').'/media-library/'", $contents);
        $this->assertStringContainsString("\$this->assertToolsNeedPermission('media-library');", $contents);
        $this->assertStringNotContainsString('{{', $contents);

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
    }

    #[Test]
    public function an_access_test_somebody_filled_in_is_kept_without_force(): void
    {
        $this->artisan('webx:make-module', ['name' => 'Pages'])->assertSuccessful();

        $path = $this->app->basePath('tests/Feature/Cms/PagesModuleAccessTest.php');
        file_put_contents($path, '<?php // filled in');

        (new Filesystem)->delete($this->directory.'/Modules/PagesModule.php');
        $this->artisan('webx:make-module', ['name' => 'Pages'])->assertSuccessful();

        $this->assertSame('<?php // filled in', file_get_contents($path));
    }

    #[Test]
    public function a_budget_test_somebody_filled_in_is_kept_without_force(): void
    {
        $this->artisan('webx:make-module', ['name' => 'Pages'])->assertSuccessful();

        $path = $this->app->basePath('tests/Feature/Cms/PagesModulePerformanceTest.php');
        file_put_contents($path, '<?php // filled in');

        (new Filesystem)->delete($this->directory.'/Modules/PagesModule.php');
        $this->artisan('webx:make-module', ['name' => 'Pages'])->assertSuccessful();

        $this->assertSame('<?php // filled in', file_get_contents($path));
    }
}
