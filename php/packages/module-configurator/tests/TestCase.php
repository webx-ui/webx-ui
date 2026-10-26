<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Configurator\ConfiguratorServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The module and the library its pictures live in. Without the inbox: a site may have none,
     * and the tests that need it add it.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            MediaServiceProvider::class,
            ConfiguratorServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'uk', 'default' => true], ['code' => 'en']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * A small, valid schema: a construction with a formula, the sizes, a glass and the hardware.
     *
     * @return array<string, mixed>
     */
    protected function schema(string $image = 'media/demo/corner.svg'): array
    {
        return [
            'version' => 1,
            'currency' => 'UAH',
            'pricing' => ['round' => 100],
            'initial' => ['construction' => 'corner'],
            'steps' => [
                ['key' => 'construction', 'type' => 'cards', 'label' => ['uk' => 'Конструкція', 'en' => 'Construction'], 'options' => [
                    ['value' => 'corner', 'label' => ['uk' => 'Кутова', 'en' => 'Corner'], 'image' => $image, 'area' => '(w+d)*h/1e6', 'price' => ['fixed' => 8000, 'perM2' => 4420], 'defaults' => ['h' => 2000, 'w' => 900, 'd' => 900]],
                    ['value' => 'niche', 'label' => ['uk' => 'У нішу', 'en' => 'Niche'], 'image' => $image, 'area' => 'w*h/1e6', 'price' => ['fixed' => 5000, 'perM2' => 4000], 'defaults' => ['h' => 2000, 'w' => 900]],
                ]],
                ['key' => 'size', 'type' => 'dimensions', 'title' => 'Розміри', 'fields' => [
                    ['key' => 'h', 'label' => 'Висота', 'unit' => 'мм', 'min' => 1300, 'max' => 2400, 'step' => 50],
                    ['key' => 'w', 'label' => 'Ширина', 'unit' => 'мм', 'min' => 600, 'max' => 1600, 'step' => 50],
                    ['key' => 'd', 'label' => 'Глибина', 'unit' => 'мм', 'min' => 600, 'max' => 1600, 'step' => 50, 'when' => ['construction' => ['corner']]],
                ]],
                ['key' => 'glass', 'type' => 'swatches', 'label' => 'Скло', 'options' => [
                    ['value' => 'clear', 'label' => 'Прозоре', 'swatch' => '#e8eef2'],
                    ['value' => 'graphite', 'label' => 'Графіт', 'swatch' => '#59616a', 'price' => ['perM2' => 600]],
                ]],
                ['key' => 'hardware', 'type' => 'swatches', 'label' => 'Фурнітура', 'options' => [
                    ['value' => 'chrome', 'label' => 'Хром', 'swatch' => '#c9ced3'],
                    ['value' => 'gold', 'label' => 'Золото', 'swatch' => 'linear-gradient(135deg, #b8862b, #f3d27a)', 'price' => ['percent' => 12]],
                ]],
            ],
        ];
    }

    /** A file the library has, so that a schema may point at it. */
    protected function mediaFile(string $path = 'media/demo/corner.svg'): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => sha1($path),
            'name' => basename($path),
            'file_name' => basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'mime' => 'image/svg+xml',
            'size' => 100,
        ]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['configurators.view', 'configurators.manage']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Editor',
            'email' => "editor-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "editor-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    protected function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
