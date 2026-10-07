<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A new slug for a type that stands on pages. Before, the row was renamed and nothing else: every
 * page printed «There is no published block type» where the block stood, the type said it was used
 * nowhere, and the toast said nothing had changed.
 */
final class RenameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);
    }

    #[Test]
    public function the_panel_renames_a_used_type_and_every_page_follows(): void
    {
        $quote = $this->publish('quote', '<q class="b-quote" data-wx-block="quote">{{ $words }}</q>', [], [
            'styles' => '.b-quote { color: red; } .b-quote__mark { color: blue; }',
        ]);
        $this->publish('section', '<section data-wx-block="section">@blocks(\'inner\')</section>', ['allow' => ['quote']], [
            'schema' => [['id' => 'inner', 'type' => 'wx-blocks']],
        ]);

        // A container whose field names the type in its schema rather than on its row.
        $this->publish('aside', '<aside data-wx-block="aside">@blocks(\'inner\')</aside>', [], [
            'schema' => [['id' => 'inner', 'type' => 'wx-blocks', 'props' => ['allow' => ['quote']]]],
        ]);

        $page = Page::query()->create(['title' => 'Test', 'blocks' => [
            ['key' => 's1', 'type' => 'section', 'values' => ['inner' => [['key' => 'q1', 'type' => 'quote', 'values' => ['words' => 'Hi']]]]],
            ['key' => 'a1', 'type' => 'aside', 'values' => ['inner' => [['key' => 'q3', 'type' => 'quote', 'values' => ['words' => 'Aside']]]]],
        ]]);
        $page->saveDraft(['blocks' => [['key' => 'q2', 'type' => 'quote', 'values' => ['words' => 'Draft']]]]);

        $answer = $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/blocks/'.$quote->id, ['slug' => 'citation'])
            ->assertOk();

        $this->assertSame('citation', $answer->json('data.renamed.to'));
        $this->assertSame(1, $answer->json('data.renamed.entities'));
        $this->assertSame(2, $answer->json('data.renamed.types'));

        $aside = Block::query()->where('slug', 'aside')->firstOrFail();
        $this->assertSame(['citation'], $aside->publishedVersion?->schema[0]['props']['allow'] ?? null, 'the container\'s field takes the new slug, published');

        // And the page still saves: the container takes the renamed block.
        $page->refresh()->storeBlocks($page->blocks);

        $page->refresh();
        $this->assertSame('citation', $page->blocks[0]['values']['inner'][0]['type']);
        $this->assertSame('citation', $page->draft['blocks'][0]['type']);
        $this->assertSame(['citation'], Block::query()->where('slug', 'section')->value('allow'));

        $renamed = Block::query()->where('slug', 'citation')->firstOrFail();
        $live = $renamed->publishedVersion;

        $this->assertNotNull($live);
        $this->assertStringContainsString('data-wx-block="citation"', (string) $live->template);
        $this->assertStringContainsString('class="b-citation"', (string) $live->template);
        $this->assertStringContainsString('.b-citation__mark', (string) $live->styles);

        $this->assertStringContainsString('<q class="b-citation" data-wx-block="citation">Hi</q>', $this->render($page->blocks));
    }

    #[Test]
    public function a_type_another_template_calls_is_not_renamed(): void
    {
        $card = $this->publish('card', '<div data-wx-block="card">{{ $title }}</div>', ['kind' => Block::KIND_COMPONENT]);
        $this->publish('grid', '<div data-wx-block="grid"><x-webx-block type="card" title="A" /></div>');

        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/blocks/'.$card->id, ['slug' => 'tile'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->assertTrue(Block::query()->where('slug', 'card')->exists());
    }

    #[Test]
    public function an_agent_renames_through_the_same_path(): void
    {
        $this->publish('quote', '<q data-wx-block="quote">{{ $words }}</q>');
        $page = Page::query()->create(['title' => 'Test', 'blocks' => [$this->node('quote', ['words' => 'Hi'], 'q1')]]);

        $this->agent('update', ['slug' => 'quote', 'rename_to' => 'citation', 'dry_run' => true])->assertOk()->assertSee('would_rename');
        $this->assertSame('quote', $page->refresh()->blocks[0]['type']);

        $this->agent('update', ['slug' => 'quote', 'rename_to' => 'citation'])->assertOk()->assertSee('citation');
        $this->assertSame('citation', $page->refresh()->blocks[0]['type']);

        $this->agent('update', ['slug' => 'citation', 'rename_to' => 'Not A Slug'])->assertHasErrors(['slug']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
    }
}
