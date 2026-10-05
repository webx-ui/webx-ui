<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Agents\AgentDocs;
use WebxUi\Admin\Agents\McpConfig;
use WebxUi\Admin\Panel\PackageRegistry;

/**
 * The site's `.mcp.json`, as `webx:panel` keeps it: one entry for this site's panel, named after
 * the site, and nothing else in the file touched.
 */
final class McpConfigTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;

        $this->app->instance(PackageRegistry::class, new PackageRegistry($this->files, __DIR__.'/Fixtures/installed.json'));
        $this->app->instance(AgentDocs::class, new AgentDocs($this->files, __DIR__.'/Fixtures/agents/composer/installed.json'));

        config(['app.url' => 'https://www.example.com']);
    }

    protected function tearDown(): void
    {
        $this->files->delete([
            $this->config(),
            $this->app->basePath('AGENTS.md'),
            $this->app->basePath('CLAUDE.md'),
            $this->app->configPath('webx-admin.php'),
        ]);
        $this->files->deleteDirectory($this->app->basePath('resources/js'));
        $this->files->deleteDirectory($this->app->basePath('resources/views/components'));

        parent::tearDown();
    }

    private function config(): string
    {
        return $this->app->basePath(McpConfig::FILE);
    }

    private function serveMcp(): void
    {
        Route::post('api/cms/mcp', static fn () => null)->name('webx.mcp');
        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        return json_decode((string) $this->files->get($this->config()), true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function it_names_the_server_after_the_site(): void
    {
        $this->serveMcp();

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertSame(
            ['mcpServers' => ['example.com' => ['type' => 'http', 'url' => 'https://www.example.com/api/cms/mcp']]],
            $this->read(),
        );

        $this->artisan('webx:panel', ['--sync' => true])
            ->expectsOutputToContain('already points at this site')
            ->assertSuccessful();
    }

    #[Test]
    public function other_servers_and_a_renamed_key_are_kept(): void
    {
        $this->serveMcp();
        $this->files->put($this->config(), json_encode(['mcpServers' => [
            'example.com (live)' => ['type' => 'http', 'url' => 'https://live.example.com/api/cms/mcp'],
            'local' => ['type' => 'http', 'url' => 'https://www.example.com/api/cms/mcp', 'headers' => ['X-Debug' => '1']],
        ]], JSON_THROW_ON_ERROR));

        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertSame(
            [
                'example.com (live)' => ['type' => 'http', 'url' => 'https://live.example.com/api/cms/mcp'],
                'local' => ['type' => 'http', 'url' => 'https://www.example.com/api/cms/mcp', 'headers' => ['X-Debug' => '1']],
            ],
            $this->read()['mcpServers'],
        );
    }

    #[Test]
    public function a_file_it_cannot_read_is_left_alone(): void
    {
        $this->serveMcp();
        $this->files->put($this->config(), '{ "mcpServers": ');

        $this->artisan('webx:panel')
            ->expectsOutputToContain('not JSON this can read')
            ->assertSuccessful();

        $this->assertSame('{ "mcpServers": ', $this->files->get($this->config()));
    }

    #[Test]
    public function without_an_mcp_endpoint_there_is_no_file(): void
    {
        $this->artisan('webx:panel')->assertSuccessful();

        $this->assertFileDoesNotExist($this->config());
    }
}
