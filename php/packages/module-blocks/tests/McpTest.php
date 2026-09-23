<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Blocks\Tests\Fixtures\ProseType;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The section by its other doors (§18): what an agent can do with block types and with the
 * content built from them, and what it is refused.
 */
final class McpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);
    }

    #[Test]
    public function the_module_offers_its_tools_resources_and_prompt(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['blocks_list', 'blocks_get', 'blocks_create', 'blocks_update', 'blocks_publish', 'blocks_render', 'blocks_get_content', 'blocks_set_content', 'blocks_edit_content', 'blocks_preview_url'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('blocks')),
        );

        // Other modules bring scopes of their own; these two are this module's.
        $this->assertContains('blocks:read', $registry->scopes());
        $this->assertContains('blocks:write', $registry->scopes());

        $this->assertSame(
            ['blocks://guidelines', 'blocks://schema', 'blocks://catalog', 'blocks://fields', 'blocks://site'],
            array_map(static fn ($resource): string => $resource->uri, $registry->resources()),
        );

        $this->assertSame('design_block', $registry->prompts()[0]->name);

        // The entity names reach the agent through the argument's description.
        $this->assertStringContainsString('note', $registry->tool('blocks_get_content')->tool->inputSchema['properties']['entity']['description']);
    }

    #[Test]
    public function an_agent_creates_a_type_renders_it_and_a_dry_run_changes_nothing(): void
    {
        $type = [
            'slug' => 'hero',
            'title' => 'Hero',
            'group' => 'layout',
            'schema' => [['id' => 'title', 'type' => 'wx-input', 'label' => 'Title']],
            'template' => '<section data-wx-block="hero" class="b-hero"><h1 class="b-hero__title">{{ $title }}</h1></section>',
            'styles' => '.b-hero { container-type: inline-size; }',
            'sample' => ['title' => 'Welcome aboard'],
        ];

        $this->agent('create', $type + ['dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['would_create'] === 'hero' && $content['warnings'] === [], (string) json_encode($content));
            });

        $this->assertSame(0, Block::query()->count());

        $this->agent('create', $type, $this->editor())
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['slug'] === 'hero'
                && $content['version']['number'] === 1
                && $content['version']['source'] === 'mcp'
                && $content['draft'] === 1
                && $content['published'] === null
                && $content['fields'] === [['id' => 'title', 'type' => 'wx-input', 'label' => 'Title']], (string) json_encode($content));
            });

        $this->assertNotNull(Block::query()->where('slug', 'hero')->first()?->draftVersion?->author_id);

        $this->agent('render', ['slug' => 'hero'])
            ->assertOk()
            ->assertSee('Welcome aboard')
            ->assertSee('data-wx-block=\"hero\"');

        $this->agent('render', ['slug' => 'hero', 'values' => ['title' => 'Other words']])
            ->assertOk()
            ->assertSee('Other words');

        $this->agent('list')
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['count'] === 1 && $content['blocks'][0]['slug'] === 'hero', (string) json_encode($content));
            });

        $this->agent('get', ['slug' => 'hero'])
            ->assertOk()
            ->assertSee('b-hero__title');
    }

    #[Test]
    public function update_writes_a_version_only_when_the_content_differs(): void
    {
        $this->publish('text', '<p data-wx-block="text">{{ $body }}</p>');

        $this->agent('update', ['slug' => 'text', 'template' => '<p data-wx-block="text" class="b-text">{{ $body }}</p>'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['wrote_version'] === true && $content['draft'] === 2 && $content['published'] === 1, (string) json_encode($content));
            });

        $this->agent('update', ['slug' => 'text', 'template' => '<p data-wx-block="text" class="b-text">{{ $body }}</p>'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['wrote_version'] === false && $content['draft'] === 2, (string) json_encode($content));
            });

        $this->agent('update', ['slug' => 'text', 'title' => 'Paragraph', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['changes'] === ['title'] && $content['would_write_version'] === false, (string) json_encode($content));
            });

        $this->assertSame('Text', Block::query()->where('slug', 'text')->value('title'));

        $this->agent('update', ['slug' => 'text', 'title' => 'Paragraph'])->assertOk();

        $this->assertSame('Paragraph', Block::query()->where('slug', 'text')->value('title'));
        $this->assertSame(2, Block::query()->where('slug', 'text')->first()?->versions()->count());
    }

    #[Test]
    public function what_is_not_accepted_is_said_and_nothing_is_written(): void
    {
        $this->agent('create', ['slug' => 'Bad Slug', 'title' => 'Bad'])
            ->assertHasErrors(['slug']);

        $this->agent('get', ['slug' => 'nothing'])
            ->assertHasErrors(['No block type is called [nothing]']);

        $this->agent('render', ['slug' => 'nothing'])
            ->assertHasErrors(['blocks_list says which exist']);

        $this->assertSame(0, Block::query()->count());
    }

    #[Test]
    public function publishing_runs_the_checks_first_and_says_where_it_failed(): void
    {
        $this->agent('create', [
            'slug' => 'broken',
            'title' => 'Broken',
            'schema' => [['id' => 'title', 'type' => 'wx-input']],
            'template' => '<div data-wx-block="broken">{{ nope($title) }}</div>',
            'sample' => ['title' => 'x'],
        ])->assertOk();

        $this->agent('publish', ['slug' => 'broken', 'dry_run' => true])
            ->assertHasErrors(['on the sample', 'nope']);

        $this->agent('publish', ['slug' => 'broken'])
            ->assertHasErrors(['Not published']);

        $this->assertNull(Block::query()->where('slug', 'broken')->value('published_version_id'));

        $this->agent('update', ['slug' => 'broken', 'template' => '<div data-wx-block="broken">{{ $title }}</div>'])->assertOk();

        $this->agent('publish', ['slug' => 'broken', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['ok'] === true && $content['version'] === 2 && $content['checked_on_pages'] === 0, (string) json_encode($content));
            });

        $this->assertNull(Block::query()->where('slug', 'broken')->value('published_version_id'));

        $this->agent('publish', ['slug' => 'broken'])
            ->assertOk()
            ->assertStructuredContent(['published' => 2, 'slug' => 'broken']);

        $this->agent('publish', ['slug' => 'broken'])
            ->assertHasErrors(['no draft to publish']);
    }

    #[Test]
    public function writing_types_is_refused_when_editing_is_off(): void
    {
        $this->app['config']->set('webx-blocks.editing', false);

        $this->agent('create', ['slug' => 'hero', 'title' => 'Hero'])->assertHasErrors(['switched off']);
        $this->agent('list')->assertOk();
    }

    #[Test]
    public function the_content_of_an_entity_is_read_and_written_as_its_draft(): void
    {
        $this->publish('text', '<p data-wx-block="text">{{ $body }}</p>');
        $this->publish('quote', '<blockquote data-wx-block="quote">{{ $words }}</blockquote>');

        $page = Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'blocks' => [$this->node('text', ['body' => 'Live words'], 'k-live')],
        ]);

        $this->agent('get_content', ['entity' => 'note', 'id' => $page->id])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['entity'] === 'note'
                && $content['title'] === 'About'
                && $content['published'] === false
                && $content['live'][0]['key'] === 'k-live'
                && $content['draft'] === null
                && $content['types'] === ['text'], (string) json_encode($content));
            });

        $tree = [
            ['type' => 'text', 'values' => ['body' => 'New words']],
            ['key' => 'kept', 'type' => 'quote', 'values' => ['words' => 'Said once']],
        ];

        $this->agent('set_content', ['entity' => 'note', 'id' => $page->id, 'blocks' => $tree, 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['would_write'] === 'draft'
                && $content['nodes'] === 2
                && $content['keys_made'] === 1
                && $content['types'] === ['text', 'quote'], (string) json_encode($content));
            });

        $this->assertNull($page->refresh()->draft);

        $this->agent('set_content', ['entity' => 'note', 'id' => $page->id, 'blocks' => $tree], $this->editor())
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json) use ($page): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['written'] === 'draft'
                && str_contains((string) $content['preview_url'], '/_preview/note/'.$page->id), (string) json_encode($content));
            });

        $page->refresh();

        $this->assertSame('Live words', $page->blocks[0]['values']['body'], 'the site still shows what it showed');
        $this->assertSame('New words', $page->draft['blocks'][0]['values']['body']);
        $this->assertSame('kept', $page->draft['blocks'][1]['key']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $page->draft['blocks'][0]['key']);
        $this->assertSame('mcp', $page->versions()->first()?->source);

        $this->agent('set_content', ['entity' => 'note', 'id' => $page->id, 'blocks' => [['type' => 'ghost', 'values' => []]]])
            ->assertHasErrors(['Unknown block type(s): ghost']);

        $this->agent('set_content', ['entity' => 'article', 'id' => 1, 'blocks' => []])
            ->assertHasErrors(['No entity is called [article]', 'note']);

        $this->agent('get_content', ['entity' => 'note', 'id' => 999])
            ->assertHasErrors(['No note has the id [999]']);

        $this->agent('preview_url', ['entity' => 'note', 'id' => $page->id, 'minutes' => 5])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json) use ($page): void {
                $content = $json->etc()->toArray();
                self::assertTrue(str_contains((string) $content['url'], '/_preview/note/'.$page->id.'?token=')
                && $content['expires_in_minutes'] === 5, (string) json_encode($content));
            });
    }

    #[Test]
    public function an_agent_changes_one_block_without_sending_the_page(): void
    {
        $this->publish('text', '<p data-wx-block="text">{{ $body }}</p>', [], [
            'schema' => [['id' => 'body', 'type' => 'wx-input', 'localized' => true]],
        ]);
        $this->publish('quote', '<blockquote data-wx-block="quote">{{ $words }}</blockquote>');

        $page = Page::query()->create([
            'title' => 'About',
            'slug' => 'about',
            'blocks' => [
                $this->node('text', ['body' => ['en' => 'Live words']], 'k-one'),
                $this->node('quote', ['words' => 'Said once'], 'k-two'),
            ],
        ]);

        // The map first: keys, types and a line each — the cheap read an edit starts from.
        $revision = Content::revision($page->refresh()->blocks);

        $this->agent('get_content', ['entity' => 'note', 'id' => $page->id, 'outline' => true])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json) use ($revision): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['outline'] === [
                    ['key' => 'k-one', 'type' => 'text', 'depth' => 0, 'label' => 'Live words'],
                    ['key' => 'k-two', 'type' => 'quote', 'depth' => 0, 'label' => 'Said once'],
                ]
                && $content['revision'] === $revision
                && ! isset($content['live']), (string) json_encode($content));
            });

        $this->agent('get_content', ['entity' => 'note', 'id' => $page->id, 'key' => 'k-two'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['node']['values']['words'] === 'Said once', (string) json_encode($content));
            });

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'revision' => $revision,
            'ops' => [
                ['op' => 'set', 'key' => 'k-one', 'values' => ['body' => 'Живые слова'], 'locale' => 'ru'],
                ['op' => 'add', 'type' => 'quote', 'values' => ['words' => 'Added'], 'after' => 'k-one'],
                ['op' => 'remove', 'key' => 'k-two'],
            ],
        ], $this->editor())->assertOk();

        $page->refresh();
        $draft = $page->draft['blocks'];

        $this->assertSame(['en' => 'Live words', 'ru' => 'Живые слова'], $draft[0]['values']['body'], 'the other language survives');
        $this->assertSame('Added', $draft[1]['values']['words']);
        $this->assertCount(2, $draft, 'the removed block is gone and nothing else moved');
        $this->assertSame('Said once', $page->blocks[1]['values']['words'], 'the site still shows what it showed');

        // The revision the agent held is the one before its own edit, so the second attempt is
        // exactly the case this guards: writing over something that has changed since.
        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'revision' => $revision,
            'ops' => [['op' => 'set', 'key' => 'k-one', 'values' => ['body' => 'Again'], 'locale' => 'ru']],
        ], $this->editor())->assertHasErrors(['changed since you read it']);

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'set', 'key' => 'ghost', 'values' => []]],
        ], $this->editor())->assertHasErrors(['Operation 1 (set)', 'key [ghost]']);

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'set', 'key' => 'k-one', 'values' => ['body' => 'One language only']]],
        ], $this->editor())->assertHasErrors(['is localized']);

        // Switching a block off goes through the same door as everything else, and the outline
        // says so — otherwise an agent has no way of telling that a block it can read is not on
        // the site.
        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'hide', 'key' => 'k-one']],
        ], $this->editor())->assertOk();

        $page->refresh();

        $this->assertTrue($page->draft['blocks'][0]['hidden']);
        $this->assertSame(['en' => 'Live words', 'ru' => 'Живые слова'], $page->draft['blocks'][0]['values']['body']);

        $this->agent('get_content', ['entity' => 'note', 'id' => $page->id, 'outline' => true], $this->editor())
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue(($content['outline'][0]['hidden'] ?? null) === true, (string) json_encode($content));
            });

        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'show', 'key' => 'k-one']],
        ], $this->editor())->assertOk();

        $this->assertArrayNotHasKey('hidden', $page->refresh()->draft['blocks'][0]);
    }

    #[Test]
    public function what_an_agent_writes_is_kept_the_way_the_field_type_keeps_it(): void
    {
        // Through the tools rather than against the walk itself: what is at stake is whether
        // the write path runs it at all, and a node rebuilt on its way through would drop
        // `hidden` without a word — which is the case the last assertions here are about.
        $this->app->make(FieldTypes::class)->register('wx-prose', new ProseType);

        $this->publish('article', '<article data-wx-block="article">{!! $body !!}</article>', [], [
            'schema' => [
                ['id' => 'lead', 'type' => 'wx-card', 'children' => [
                    ['id' => 'body', 'type' => 'wx-prose', 'localized' => true],
                ]],
                ['id' => 'note', 'type' => 'wx-prose'],
                ['id' => 'inside', 'type' => 'wx-blocks'],
            ],
        ]);
        $this->publish('quote', '<blockquote data-wx-block="quote">{{ $words }}</blockquote>');

        $page = Page::query()->create(['title' => 'About', 'slug' => 'about']);

        $this->agent('set_content', [
            'entity' => 'note',
            'id' => $page->id,
            'blocks' => [[
                'key' => 'k-one',
                'type' => 'article',
                'hidden' => true,
                'values' => [
                    'body' => ['en' => '<p>Hello<script>steal()</script></p>', 'ru' => '<p></p>'],
                    'note' => '<p>Kept</p>',
                    'gone' => '<p>A field the schema no longer names<script>steal()</script></p>',
                    'inside' => [['key' => 'k-two', 'type' => 'quote', 'values' => ['words' => '<b>Said once</b>']]],
                ],
            ]],
        ], $this->editor())->assertOk();

        $values = $page->refresh()->draft['blocks'][0]['values'];

        $this->assertSame('<p>Hello</p>', $values['body']['en'], 'the allowlist runs per language');
        $this->assertNull($values['body']['ru'], 'a document with nothing left in it is null, not its own empty tags');
        $this->assertSame('<p>Kept</p>', $values['note']);
        $this->assertSame(
            '<p>A field the schema no longer names<script>steal()</script></p>',
            $values['gone'],
            'nobody knows what a value the schema does not name means, so nothing is done to it',
        );

        // The nested tree is the renderer's, not a field type's: `wx-prose` is not asked what
        // to make of a list of blocks. It is walked as blocks, and the one inside is kept by
        // its own schema, which says `words` is an input and not prose.
        $this->assertSame('<b>Said once</b>', $values['inside'][0]['values']['words']);
        $this->assertSame('k-two', $values['inside'][0]['key']);

        $this->assertTrue($page->draft['blocks'][0]['hidden'], 'the node keeps every key it came with');
        $this->assertSame('k-one', $page->draft['blocks'][0]['key']);
    }

    #[Test]
    public function the_http_door_needs_a_token_and_the_token_needs_the_scope(): void
    {
        $editor = $this->editor();

        $this->postJson('/api/cms/mcp', $this->rpc('tools/list'))->assertUnauthorized();

        $listed = $this->bearing($editor, ['blocks:read'])
            ->postJson('/api/cms/mcp', $this->rpc('tools/list'))
            ->assertOk()
            ->json('result.tools.*.name');

        $this->assertContains('blocks_list', $listed);

        $create = $this->rpc('tools/call', ['name' => 'blocks_create', 'arguments' => ['slug' => 'hero', 'title' => 'Hero']]);

        $this->bearing($editor, ['blocks:read'])
            ->postJson('/api/cms/mcp', $create)
            ->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', static fn (string $text): bool => str_contains($text, '[blocks:write]'));

        $this->assertSame(0, Block::query()->count());

        $created = $this->bearing($editor, ['blocks:read', 'blocks:write'])
            ->postJson('/api/cms/mcp', $create)
            ->assertOk()
            ->json();

        $this->assertSame('hero', data_get($created, 'result.structuredContent.slug'), (string) json_encode($created));

        $this->assertSame($editor->getKey(), Block::query()->where('slug', 'hero')->first()?->draftVersion?->author_id);

        // Switched off since the token was issued: the token stops working with the account.
        $editor->forceFill(['is_active' => false])->save();

        $this->bearing($editor, ['blocks:read'])
            ->postJson('/api/cms/mcp', $this->rpc('tools/list'))
            ->assertForbidden();
    }

    #[Test]
    public function a_token_granted_over_oauth_reaches_the_module(): void
    {
        // What a client is actually handed: one scope for the whole server, because that is
        // the only one it is ever offered there. Reading module scopes off such a token
        // would refuse every call — silently, and only once somebody connected for real.
        $this->bearing($this->editor(), ['mcp:use'])
            ->postJson('/api/cms/mcp', $this->rpc('tools/call', [
                'name' => 'blocks_create',
                'arguments' => ['slug' => 'hero', 'title' => 'Hero'],
            ]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.slug', 'hero');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $registry = $this->app->make(ToolRegistry::class);
        $bound = new RegistryTool($registry->tool('blocks_'.$tool));

        return $as instanceof CmsUser
            ? WebxServer::actingAs($as, 'cms')->tool($bound, $arguments)
            : WebxServer::tool($bound, $arguments);
    }

    /**
     * The next request as this administrator, with a token carrying these scopes.
     *
     * `Passport::actingAs()` rather than a real one: issuing a token for real needs the
     * site's keys and a personal access client, and what is being checked here is the door,
     * not the crypto. The guard caches the user it resolved for the last request, as it
     * would within one; between two requests of one test it has to forget.
     *
     * @param  list<string>  $scopes
     */
    private function bearing(CmsUser $editor, array $scopes): static
    {
        $this->app['auth']->forgetGuards();

        Passport::actingAs($editor, $scopes, 'api');

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
    }
}
