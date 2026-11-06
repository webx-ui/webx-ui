<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Lang;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Help\HelpBuilder;
use WebxUi\Admin\Help\HelpRegistry;
use WebxUi\Admin\Help\Mcp\HelpModule;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Localization\Contracts\HasPanelLocale;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryResource;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Mcp\Tool;

/**
 * The help for an agent (WEBX_UI_PANEL_HELP.md §5): the same files and the same tree as the
 * panel's, through `help_list`, `help_get` and `help://screens`.
 */
final class HelpMcpTest extends TestCase
{
    use MigratesOnce;

    private const string FIXTURES = __DIR__.'/Fixtures/help/';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class, AuthServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $help = $this->app->make(HelpRegistry::class);
        $help->directory(self::FIXTURES.'owner');
        $help->directory(self::FIXTURES.'dialog');

        Screens::register('shop.item-form', [
            ['id' => 'tabs', 'type' => 'wx-tabs', 'children' => [
                ['id' => 'general', 'type' => 'wx-tab', 'label' => 'trans::webx-admin::nav.system', 'children' => [
                    ['id' => 'code', 'type' => 'wx-input', 'name' => 'stock.code', 'label' => 'Code', 'help' => 'Latin letters.'],
                    ['id' => 'price', 'type' => 'wx-input', 'name' => 'price', 'label' => 'Price', 'can' => 'shop.prices'],
                ]],
            ]],
        ]);

        Screens::register('shop.item-dialog', [
            ['id' => 'note', 'type' => 'wx-textarea', 'name' => 'note', 'label' => 'Note'],
        ]);
    }

    #[Test]
    public function the_tools_are_named_after_the_module_and_read_with_its_scope(): void
    {
        $tools = $this->app->make(ToolRegistry::class)->toolsOf('help');

        $this->assertSame(['help_list', 'help_get'], array_map(static fn ($tool): string => $tool->fullName(), $tools));
        $this->assertSame(['help:read'], $tools[0]->scopes());
        $this->assertFalse($tools[1]->tool->mutating);
    }

    #[Test]
    public function the_tools_stand_behind_any_permission_a_module_declares(): void
    {
        $declared = $this->app->make(AccessPolicy::class)->declared();

        $this->assertNotSame([], $declared);

        foreach ($this->module()->mcpTools() as $tool) {
            $this->assertSame($declared, $tool->permissions);
        }

        WebxServer::actingAs(new Editor($declared))->tool($this->served('get'), ['key' => 'shop.item-form'])->assertOk();
        WebxServer::actingAs(new Editor)->tool($this->served('get'), ['key' => 'shop.item-form'])->assertHasErrors(['does not hold']);
    }

    #[Test]
    public function the_list_says_what_there_is_to_read(): void
    {
        $answer = $this->ask('list', [], new Editor);

        $this->assertSame('en', $answer['locale']);
        $this->assertContains([
            'key' => 'shop.item-form',
            'title' => 'Item',
            'kind' => 'screen',
            'has_fields' => true,
            'module' => 'shop',
        ], $answer['screens']);
    }

    #[Test]
    public function a_page_with_no_tree_is_a_page_and_its_fields_come_from_the_file(): void
    {
        $answer = $this->ask('list', [], null);
        $row = array_column($answer['screens'], null, 'key')['shop.list'];

        $this->assertSame('page', $row['kind'] ?? null);
        $this->assertTrue($row['has_fields'] ?? null);

        $field = $this->ask('get', ['key' => 'shop.list'], null)['groups'][0]['fields'][0];

        $this->assertNull($field['name']);
        $this->assertNull($field['type']);
    }

    #[Test]
    public function a_field_tells_an_agent_its_name_and_type_and_the_panel_nothing_of_the_kind(): void
    {
        $page = $this->ask('get', ['key' => 'shop.item-form'], new Editor(['shop.prices']));
        $field = $page['groups'][0]['fields'][0];

        $this->assertSame('stock.code', $field['name']);
        $this->assertSame('wx-input', $field['type']);
        $this->assertSame('What the code changes on the site.', $field['body']);

        $panel = $this->app->make(HelpBuilder::class)->build('shop.item-form', 'en', static fn (): bool => true, static fn (string $words): string => $words);

        $this->assertArrayNotHasKey('name', $panel['groups'][0]['fields'][0] ?? []);
        $this->assertArrayNotHasKey('type', $panel['groups'][0]['fields'][0] ?? []);
    }

    #[Test]
    public function can_cuts_a_field_as_in_the_panel_and_nobody_on_stdio_sees_them_all(): void
    {
        $ids = static fn (array $page): array => array_column($page['groups'][0]['fields'], 'id');

        $this->assertSame(['code'], $ids($this->ask('get', ['key' => 'shop.item-form'], new Editor)));
        $this->assertSame(['code', 'price'], $ids($this->ask('get', ['key' => 'shop.item-form'], new Editor(['shop.prices']))));
        $this->assertSame(['code', 'price'], $ids($this->ask('get', ['key' => 'shop.item-form'], null)));
    }

    #[Test]
    public function the_language_is_the_one_asked_for_with_the_labels_in_it_too(): void
    {
        config(['webx-localization.panel' => ['en', 'ru', 'de']]);
        Lang::addLines(['nav.system' => 'Система'], 'ru', 'webx-admin');

        $russian = $this->ask('get', ['key' => 'shop.item-form', 'locale' => 'ru'], new Editor);

        $this->assertSame('ru', $russian['locale']);
        $this->assertSame('Система', $russian['groups'][0]['label']);

        // The chain of §2.5: no text in that language is the text in English.
        $this->assertSame('en', $this->ask('get', ['key' => 'shop.item-form', 'locale' => 'de'], new Editor)['locale']);
    }

    #[Test]
    public function without_a_language_it_is_the_administrators_own(): void
    {
        config(['webx-localization.panel' => ['en', 'ru']]);

        $russian = new class(['id' => 1]) extends GenericUser implements HasPanelLocale
        {
            public function panelLocale(): string
            {
                return 'ru';
            }
        };

        $this->assertSame('ru', $this->ask('get', ['key' => 'shop.item-form'], $russian)['locale']);
    }

    #[Test]
    public function a_language_that_is_not_one_goes_nowhere_near_a_path(): void
    {
        $page = $this->ask('get', ['key' => 'shop.item-form', 'locale' => '../../secrets'], new Editor);

        $this->assertSame('en', $page['locale']);
    }

    #[Test]
    public function an_unknown_key_says_where_to_look(): void
    {
        $this->expectException(ToolFailure::class);
        $this->expectExceptionMessage('help_list');

        $this->ask('get', ['key' => 'shop.nothing'], new Editor);
    }

    #[Test]
    public function a_missing_key_is_refused_in_words(): void
    {
        $this->expectException(ToolFailure::class);
        $this->expectExceptionMessage('`key`');

        $this->ask('get', [], new Editor);
    }

    #[Test]
    public function a_broken_file_is_a_refusal_that_names_no_path_and_the_list_goes_on(): void
    {
        $this->app->make(HelpRegistry::class)->directory(self::FIXTURES.'broken');

        $keys = array_column($this->ask('list', [], new Editor)['screens'], 'key');

        $this->assertNotContains('shop.item-form', $keys);
        $this->assertContains('shop.list', $keys);

        try {
            $this->ask('get', ['key' => 'shop.item-form'], new Editor);
            $this->fail('A broken part is not an answer.');
        } catch (ToolFailure $failure) {
            $this->assertStringNotContainsString('/', $failure->getMessage());
        }
    }

    #[Test]
    public function the_resource_is_the_list_and_the_template_one_markdown_document(): void
    {
        $this->assertSame('application/json', $this->resource('help://screens')->mimeType);
        $this->assertSame('text/markdown', $this->resource('help://screens/{key}')->mimeType);

        WebxServer::actingAs(new Editor)
            ->resource(RegistryResource::for($this->resource('help://screens')))
            ->assertOk()
            ->assertSee('"key": "shop.item-form"');

        WebxServer::actingAs(new Editor)
            ->resource(RegistryResource::for($this->resource('help://screens/{key}')), ['key' => 'shop.item-form'])
            ->assertOk()
            ->assertSee(['# Item', 'Why the section exists.', '### How to add an item', '### Code (`stock.code`)', 'What the code changes on the site.'])
            ->assertDontSee('What the price changes on the site.');
    }

    #[Test]
    public function the_template_is_found_by_an_address_with_dots_in_it(): void
    {
        $template = RegistryResource::for($this->resource('help://screens/{key}'));

        $this->assertInstanceOf(HasUriTemplate::class, $template);
        $this->assertSame(['key' => 'catalog.exchange.profiles'], $template->uriTemplate()->match('help://screens/catalog.exchange.profiles'));
    }

    #[Test]
    public function the_template_answers_an_unknown_key_with_a_refusal(): void
    {
        $this->expectException(ToolFailure::class);

        ($this->resource('help://screens/{key}')->handler)(['key' => 'shop.nothing'], new Editor);
    }

    #[Test]
    public function the_instructions_send_an_agent_to_the_help(): void
    {
        $server = $this->app->make(WebxServer::class, ['transport' => new FakeTransporter]);
        $server->start();

        $this->assertStringContainsString('`help_get`', $server->createContext()->instructions);
    }

    private function module(): HelpModule
    {
        $module = $this->app->make(ModuleRegistry::class)->get('help');
        $this->assertInstanceOf(HelpModule::class, $module);

        return $module;
    }

    private function tool(string $name): Tool
    {
        foreach ($this->module()->mcpTools() as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }

        $this->fail("No tool {$name}.");
    }

    private function served(string $name): RegistryTool
    {
        return new RegistryTool($this->app->make(ToolRegistry::class)->tool('help_'.$name));
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->module()->mcpResources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource {$uri}.");
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function ask(string $tool, array $arguments, ?Authenticatable $user): array
    {
        return ($this->tool($tool)->handler)($arguments, $user);
    }
}
