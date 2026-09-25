<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\BlockShapes;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Components by the agent's door (§3.11 of the components spec): what the tools say about who
 * calls whom, "Customise" through `blocks_create`, and a refusal that names the parent and page.
 */
final class McpComponentsTest extends TestCase
{
    private string $views;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);

        $this->views = sys_get_temp_dir().'/webx-mcp-components-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->views.'/partials');
        File::put($this->views.'/partials/card.blade.php', '<li class="card">{{ $card[\'title\'] }}</li>');
        View::addNamespace('webx-demo', $this->views);

        $this->app->make(BlockShapes::class)->register(
            'recipes.card',
            fields: [['name' => 'title', 'type' => 'string', 'description' => 'What it is called']],
            sample: static fn (): array => ['title' => 'Borscht'],
        );

        $this->app->make(BlockComponents::class)->declare(
            slug: 'recipe-card',
            module: 'recipes',
            fallback: 'webx-demo::partials.card',
            title: 'Recipe card',
            description: 'One recipe in a list.',
            schema: [['type' => 'wx-data', 'id' => 'card', 'label' => 'Recipe', 'props' => ['shape' => 'recipes.card']]],
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->views);

        parent::tearDown();
    }

    #[Test]
    public function the_list_says_the_kind_who_calls_whom_and_what_modules_declared(): void
    {
        $this->publish('badge', '<b>{{ $label }}</b>', ['kind' => 'component']);
        $this->publish('hero', '<section><x-webx-block type="badge" label="New" /></section>');

        $this->agent('list')
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                $rows = array_column($content['blocks'], null, 'slug');

                self::assertSame('component', $rows['badge']['kind']);
                self::assertSame('block', $rows['hero']['kind']);
                self::assertSame(['hero'], array_column($rows['badge']['used_by'], 'slug'));
                self::assertSame([], $rows['hero']['used_by']);
                self::assertSame([[
                    'slug' => 'recipe-card',
                    'module' => 'recipes',
                    'title' => 'Recipe card',
                    'description' => 'One recipe in a list.',
                    'fallback' => 'webx-demo::partials.card',
                    'customised' => false,
                ]], $content['declared']);
            });

        $this->agent('get', ['slug' => 'hero'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                self::assertSame(['badge'], $content['uses']);
                self::assertNull($content['declared']);
            });
    }

    #[Test]
    public function create_on_a_declared_slug_without_a_template_customises_it(): void
    {
        $this->agent('create', ['slug' => 'recipe-card', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(['dry_run' => true, 'would_customise' => 'recipe-card']);

        $this->assertSame(0, Block::query()->count());

        $this->agent('create', ['slug' => 'recipe-card'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                self::assertTrue($content['customised']);
                self::assertSame('component', $content['kind']);
                self::assertSame('Recipe card', $content['title']);
                self::assertStringContainsString('class="card"', $content['content']['template']);
                self::assertSame(['card' => ['title' => 'Borscht']], $content['content']['sample']);
                self::assertSame('recipes', $content['declared']['module']);
                self::assertSame('What it is called', $content['shape']['recipes.card']['fields'][0]['description']);
            });

        $block = Block::query()->where('slug', 'recipe-card')->firstOrFail();

        $this->assertNull($block->published_version_id);
        $this->assertSame(BlockVersion::SOURCE_MCP, $block->draftVersion?->source);

        $this->agent('create', ['slug' => 'recipe-card'])->assertHasErrors(['customised already']);

        $this->agent('render', ['slug' => 'recipe-card'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                self::assertStringContainsString('Borscht', (string) $json->etc()->toArray()['html']);
            });
    }

    #[Test]
    public function a_component_is_made_by_kind_and_cannot_become_one_while_pages_use_it(): void
    {
        $this->agent('create', [
            'slug' => 'badge',
            'kind' => 'component',
            'title' => 'Badge',
            'schema' => [['id' => 'label', 'type' => 'wx-input']],
            'template' => '<b>{{ $label }}</b>',
            'sample' => ['label' => 'New'],
        ])->assertOk()->assertStructuredContent(static function (AssertableJson $json): void {
            self::assertSame('component', $json->etc()->toArray()['kind']);
        });

        $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');
        Page::query()->create(['title' => 'Home', 'blocks' => [['key' => 'a', 'type' => 'hero', 'values' => ['title' => 'Hi']]]]);

        $this->agent('update', ['slug' => 'hero', 'kind' => 'component'])->assertHasErrors(['kind']);
        $this->assertSame('block', Block::query()->where('slug', 'hero')->value('kind'));
    }

    #[Test]
    public function a_refused_publication_names_the_parent_and_the_page(): void
    {
        $this->publish('badge', '<b>{{ $label }}</b>', ['kind' => 'component'], [
            'schema' => [['id' => 'label', 'type' => 'wx-input']],
            'sample' => ['label' => 'x'],
        ]);
        $this->publish('hero', '<section data-wx-block="hero"><x-webx-block type="badge" :label="$title" /></section>', [], [
            'schema' => [['id' => 'title', 'type' => 'wx-input']],
            'sample' => ['title' => 'Sample'],
        ]);
        Page::query()->create(['title' => 'Home', 'blocks' => [['key' => 'a', 'type' => 'hero', 'values' => ['title' => 'Hi']]]]);

        $this->agent('update', ['slug' => 'badge', 'template' => '<b>{{ nope($label) }}</b>'])->assertOk();

        $this->agent('publish', ['slug' => 'badge'])->assertHasErrors(['Not published', 'Hero', 'nope']);
        $this->assertSame(1, Block::query()->where('slug', 'badge')->firstOrFail()->publishedVersion?->number);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $registry = $this->app->make(ToolRegistry::class);

        return WebxServer::tool(new RegistryTool($registry->tool('blocks_'.$tool)), $arguments);
    }
}
