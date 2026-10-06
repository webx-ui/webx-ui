<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Values for fields a block type does not have — left by an import or by a field taken out of
 * the type: how a block is told apart without them, and the two ways of taking them out.
 */
final class StrayValuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);

        $this->publish('hero', '<section data-wx-block="hero">{{ $heading }}</section>', [], ['schema' => [
            ['id' => 'image', 'type' => 'wx-media'],
            ['id' => 'button_url', 'type' => 'wx-input'],
            ['id' => 'heading', 'type' => 'wx-input', 'localized' => true],
        ]]);
        $this->publish('reviews', '<section data-wx-block="reviews">{{ $layout }}</section>', [], ['schema' => [
            ['id' => 'layout', 'type' => 'wx-segmented'],
            ['id' => 'note', 'type' => 'wx-rich-text'],
        ]]);
    }

    #[Test]
    public function the_outline_names_a_block_by_what_its_fields_mean(): void
    {
        $page = $this->page([
            // A stray demo title first, a picture and an address before the heading.
            $this->node('hero', ['title' => 'Pages made of blocks', 'image' => 'media/8d/1e/a.png', 'button_url' => '/cms', 'heading' => ['en' => 'Deeply heard']], 'k-hero'),
            // A setting first, then a text in markup.
            $this->node('reviews', ['layout' => 'grid', 'note' => '<p>What <b>clients</b> say</p>'], 'k-reviews'),
            // Nothing that reads as a name: the type's own title.
            $this->node('reviews', ['layout' => 'grid'], 'k-empty'),
        ]);

        $this->agent('get_content', ['entity' => 'note', 'id' => $page->id, 'outline' => true])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $labels = array_column($json->etc()->toArray()['outline'], 'label', 'key');
                self::assertSame(['k-hero' => 'Deeply heard', 'k-reviews' => 'What clients say', 'k-empty' => 'Reviews'], $labels);
            });
    }

    #[Test]
    public function an_agent_takes_values_out_of_a_block_and_a_dry_run_changes_nothing(): void
    {
        $page = $this->page([$this->node('hero', ['title' => 'Stray', 'subtitle' => 'Also stray', 'heading' => ['en' => 'Kept']], 'k-hero')]);
        $op = ['op' => 'unset', 'key' => 'k-hero', 'fields' => ['title', 'subtitle']];

        $this->agent('edit_content', ['entity' => 'note', 'id' => $page->id, 'ops' => [$op], 'dry_run' => true])->assertOk();
        $this->assertArrayHasKey('title', $this->values($page));

        $this->agent('edit_content', ['entity' => 'note', 'id' => $page->id, 'ops' => [$op]])->assertOk();
        $this->assertSame(['heading' => ['en' => 'Kept']], $this->values($page));

        $this->agent('edit_content', ['entity' => 'note', 'id' => $page->id, 'ops' => [['op' => 'unset', 'key' => 'k-hero']]])
            ->assertHasErrors(['`fields` is required by unset']);
    }

    #[Test]
    public function the_prune_command_takes_out_what_no_type_defines_live_and_in_the_draft(): void
    {
        $nested = $this->node('reviews', ['layout' => 'grid', 'rich-text' => 'stray'], 'k-inner');
        $page = $this->page([
            $this->node('hero', ['title' => 'Stray', 'heading' => ['en' => 'Kept'], 'inside' => [$nested]], 'k-hero'),
            $this->node('gone-type', ['anything' => 'left alone'], 'k-unknown'),
        ]);

        $page->saveDraft(['blocks' => [$this->node('hero', ['title' => 'In the draft', 'heading' => ['en' => 'Next']], 'k-hero')]]);

        $this->artisan('webx:blocks:prune', ['--dry-run' => true])->assertSuccessful();
        $this->assertArrayHasKey('title', $this->values($page));

        $this->artisan('webx:blocks:prune')->assertSuccessful();

        $this->assertSame(['heading' => ['en' => 'Next']], $this->values($page), 'the draft is cleaned too');

        $blocks = $page->refresh()->blocks;
        $this->assertSame(['heading' => ['en' => 'Kept']], $blocks[0]['values'], 'the nested list was a stray field too');
        $this->assertSame(['anything' => 'left alone'], $blocks[1]['values'], 'no schema to measure an unknown type by');

        $this->artisan('webx:blocks:prune')->expectsOutputToContain('only the fields its type defines')->assertSuccessful();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(array $blocks): Page
    {
        return Page::query()->create(['title' => 'About', 'slug' => 'about', 'blocks' => $blocks]);
    }

    /**
     * The first block as an edit sees it: the draft's when there is one.
     *
     * @return array<string, mixed>
     */
    private function values(Page $page): array
    {
        $page->refresh();
        $blocks = $page->draftValues()['blocks'] ?? $page->blocks;

        return $blocks[0]['values'];
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
