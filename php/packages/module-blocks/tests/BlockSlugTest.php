<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A slug is brought to its shape rather than refused (#125): whatever is typed — in the panel, by
 * an agent, by a direct save — becomes `[a-z][a-z0-9-]*`, and only a slug nothing is left of or
 * one already taken is refused.
 */
final class BlockSlugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);
        Locale::fromCode('ru', ['is_default' => true, 'sort' => 0])->save();
        $this->app->make(Locales::class)->forget();
    }

    #[Test]
    public function the_panel_makes_a_slug_of_what_was_typed(): void
    {
        $editor = $this->editor();

        $id = (int) $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks', ['slug' => 'Тормозные Колодки', 'title' => 'Pads'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'tormoznye-kolodki')
            ->json('data.id');

        // A slug must start with a letter: one that starts with a digit is put after `block`.
        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks', ['slug' => '2 колонки', 'title' => 'Columns'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'block-2-kolonki');

        // A rename is brought the same way, and a taken slug is still refused.
        $this->actingAs($editor, 'cms')
            ->putJson("/api/cms/blocks/{$id}", ['slug' => 'Brake Pads'])
            ->assertOk();

        $this->assertSame('brake-pads', Block::query()->findOrFail($id)->slug);

        $this->actingAs($editor, 'cms')
            ->putJson("/api/cms/blocks/{$id}", ['slug' => 'Block 2 Kolonki'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        // Nothing is left of signs alone: refused, never saved as an empty slug.
        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks', ['slug' => '!!!', 'title' => 'Signs'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($editor, 'cms')
            ->putJson("/api/cms/blocks/{$id}", ['slug' => '!!!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->assertSame('brake-pads', Block::query()->findOrFail($id)->slug);
    }

    #[Test]
    public function an_agent_and_a_direct_save_get_the_same_slug(): void
    {
        $this->agent('create', ['slug' => 'Тормозные колодки', 'title' => 'Pads'])->assertOk()->assertSee('tormoznye-kolodki');

        $this->assertTrue(Block::query()->where('slug', 'tormoznye-kolodki')->exists());

        $this->agent('create', ['slug' => 'Tormoznye Kolodki', 'title' => 'Again'])->assertHasErrors(['slug']);

        $this->agent('update', ['slug' => 'tormoznye-kolodki', 'rename_to' => 'Новые колодки'])->assertOk();

        $this->assertSame(['novye-kolodki'], Block::query()->pluck('slug')->all());

        $block = Block::query()->create(['slug' => '3 Колонки', 'title' => 'Columns']);

        $this->assertSame('block-3-kolonki', $block->slug);
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
