<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;

final class PanelCommandTest extends TestCase
{
    private Filesystem $files;

    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            $this->files->delete($path);
        }

        // The command publishes the configuration into the skeleton, and a leftover copy with
        // a Vite entry in it makes every other test render a shell that looks for a build.
        $this->files->delete($this->app->configPath('webx-admin.php'));

        $this->files->deleteDirectory($this->app->basePath('resources/js'));

        parent::tearDown();
    }

    private function at(string $relative): string
    {
        $path = $this->app->basePath($relative);
        $this->written[] = $path;

        return $path;
    }

    private function viteConfig(string $contents): string
    {
        $path = $this->at('vite.config.js');
        $this->files->put($path, $contents);

        return $path;
    }

    #[Test]
    public function it_writes_an_entry_file(): void
    {
        $entry = $this->at('resources/js/admin.ts');

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertFileExists($entry);

        $contents = (string) $this->files->get($entry);

        $this->assertStringContainsString("from '@webx-ui/admin'", $contents);
        $this->assertStringContainsString("basePath: '/cms'", $contents);
    }

    #[Test]
    public function the_entry_follows_the_panel_path(): void
    {
        config()->set('webx-admin.path', 'panel');
        $entry = $this->at('resources/js/admin.ts');

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertStringContainsString("basePath: '/panel'", (string) $this->files->get($entry));
    }

    #[Test]
    public function it_refuses_to_overwrite_without_being_told_to(): void
    {
        $entry = $this->at('resources/js/admin.ts');
        $this->files->ensureDirectoryExists(dirname($entry));
        $this->files->put($entry, '// mine');

        $this->artisan('webx:panel')->assertFailed();
        $this->assertSame('// mine', $this->files->get($entry));

        $this->artisan('webx:panel', ['--force' => true])->assertSuccessful();
        $this->assertStringNotContainsString('// mine', (string) $this->files->get($entry));
    }

    #[Test]
    public function it_adds_the_entry_to_a_list_of_vite_inputs(): void
    {
        $this->at('resources/js/admin.ts');
        $config = $this->viteConfig(<<<'JS'
            import laravel from 'laravel-vite-plugin'

            export default defineConfig({
                plugins: [
                    laravel({
                        input: ['resources/css/app.css', 'resources/js/app.js'],
                        refresh: true,
                    }),
                ],
            })
            JS);

        $this->artisan('webx:panel')->assertSuccessful();

        $contents = (string) $this->files->get($config);

        $this->assertStringContainsString("'resources/js/admin.ts',", $contents);
        // The inputs that were there have to survive.
        $this->assertStringContainsString("'resources/js/app.js'", $contents);
        $this->assertStringContainsString('refresh: true', $contents);
    }

    #[Test]
    public function it_turns_a_single_vite_input_into_a_list(): void
    {
        $this->at('resources/js/admin.ts');
        $config = $this->viteConfig("laravel({ input: 'resources/js/app.js' })");

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertStringContainsString(
            "input: ['resources/js/app.js', 'resources/js/admin.ts']",
            (string) $this->files->get($config),
        );
    }

    #[Test]
    public function it_leaves_a_config_that_already_builds_the_entry_alone(): void
    {
        $this->at('resources/js/admin.ts');
        $before = "laravel({ input: ['resources/js/admin.ts'] })";
        $config = $this->viteConfig($before);

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertSame($before, $this->files->get($config));
    }

    #[Test]
    public function it_says_what_to_add_rather_than_guessing_at_an_unfamiliar_config(): void
    {
        // Rewriting somebody's build on a guess is worse than one line of instruction.
        $this->at('resources/js/admin.ts');
        $before = 'export default defineConfig({ plugins: [vue()] })';
        $config = $this->viteConfig($before);

        $this->artisan('webx:panel')
            ->expectsOutputToContain("add 'resources/js/admin.ts'")
            ->assertSuccessful();

        $this->assertSame($before, $this->files->get($config));
    }

    #[Test]
    public function the_example_in_the_configuration_comment_is_not_mistaken_for_the_setting(): void
    {
        $this->at('resources/js/admin.ts');

        $this->artisan('webx:panel')->assertSuccessful();

        $published = (string) $this->files->get($this->app->configPath('webx-admin.php'));

        // The published file documents this key with the very path the command writes, so a
        // text search finds the comment and concludes there is nothing to do.
        $this->assertStringContainsString("'vite' => ['resources/js/admin.ts'],", $published);
        $this->assertStringNotContainsString("'vite' => [],", $published);
    }

    #[Test]
    public function it_says_what_to_install(): void
    {
        $this->at('resources/js/admin.ts');

        $this->artisan('webx:panel')
            ->expectsOutputToContain('@webx-ui/admin')
            ->assertSuccessful();
    }

    #[Test]
    public function the_entry_can_go_somewhere_else(): void
    {
        $entry = $this->at('resources/js/panel/main.ts');

        $this->artisan('webx:panel', ['--entry' => 'resources/js/panel/main.ts'])
            ->assertSuccessful();

        $this->assertFileExists($entry);
    }
}
