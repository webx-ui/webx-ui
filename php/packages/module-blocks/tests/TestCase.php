<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\PassportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use phpseclib4\Crypt\RSA;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Blocks\Tests\Fixtures\PageHandler;
use WebxUi\Blocks\Tests\Fixtures\RoutedPage;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\RoutingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            // Passport is what an agent's token is; the mcp package is the door it comes through.
            PassportServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');

        // `php artisan passport:keys` on a site. The `api` guard is built before it is asked
        // anything, and building it reads the public key — so without these even a call with
        // no token at all is a 500 rather than a 401.
        [$private, $public] = self::keys();
        $app['config']->set('passport.private_key', $private);
        $app['config']->set('passport.public_key', $public);

        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['blocks.view', 'blocks.manage']): CmsUser
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
     * One keypair for the whole run: generating an RSA key is the slowest thing in this file.
     *
     * @return array{string, string}
     */
    private static function keys(): array
    {
        static $keys = null;

        if ($keys === null) {
            // The library `passport:keys` itself uses, so these are the keys a site gets.
            $key = RSA::createKey(2048);

            $keys = [(string) $key, (string) $key->getPublicKey()];
        }

        return $keys;
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->blocks();
            $table->draft();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        // What a content module registers in its own provider: the type the preview route and
        // the resolver both hand a page to. `note` is the same without an address, for the
        // preview of a record the registry knows nothing about.
        $types = $this->app->make(RouteTypes::class);

        $types->register(new RouteType(
            type: 'page',
            model: RoutedPage::class,
            formatter: Slug::class,
            handler: PageHandler::class,
        ));

        $types->register(new RouteType(
            type: 'note',
            model: Page::class,
            formatter: Slug::class,
            handler: PageHandler::class,
        ));
    }

    protected function tearDown(): void
    {
        // One compiled file per version, and every test starts its versions at 1: a file left
        // by the previous test would be served for a template it was not compiled from.
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }

    /**
     * A block type with one published version, the way the panel would have made it.
     *
     * Unless the test says otherwise, the schema declares every `$variable` the template
     * mentions: the renderer only knows the fields the schema names, and a test about
     * rendering should not have to spell out a field list to get its template to run.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $content
     */
    protected function publish(string $slug, string $template, array $attributes = [], array $content = []): Block
    {
        $block = Block::query()->create(['slug' => $slug, 'title' => ucfirst($slug)] + $attributes);

        if (! isset($content['schema'])) {
            preg_match_all('/\$([a-z][a-z0-9_]*)/i', $template, $found);

            $content['schema'] = array_map(
                static fn (string $id): array => ['id' => $id, 'type' => 'wx-input'],
                array_values(array_diff(array_unique($found[1]), ['block', 'entity', '__env'])),
            );
        }

        $block->saveVersion(['template' => $template] + $content);
        $block->publish();

        return $block->refresh();
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     */
    protected function render(array $blocks, ?object $entity = null, bool $preview = false): string
    {
        return (string) $this->app->make(Renderer::class)->preview($preview)->render($blocks, $entity);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function node(string $type, array $values = [], ?string $key = null): array
    {
        static $count = 0;
        $count++;

        return ['key' => $key ?? "k{$count}", 'type' => $type, 'values' => $values];
    }
}
