<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * What the panel does with blocks, an agent does too: delete a type, read and restore its
 * history, see where it stands; discard, read, restore and adopt a region. Each with the panel's
 * checks and a dry run.
 */
final class McpParityTest extends RegionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);
    }

    #[Test]
    public function a_type_is_deleted_only_where_the_panel_would_delete_it(): void
    {
        $this->publish('quote', '<q data-wx-block="quote">{{ $words }}</q>');
        $page = Page::query()->create(['title' => 'Quotes', 'blocks' => [$this->node('quote', ['words' => 'Hi'], 'q1')]]);

        $this->agent('delete', ['slug' => 'quote'])->assertHasErrors(['stands on 1']);

        $this->agent('usage', ['slug' => 'quote'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json) use ($page): void {
                $usage = $json->etc()->toArray();
                self::assertTrue($usage['count'] === 1 && $usage['entities'][0]['entity'] === 'note'
                    && (int) $usage['entities'][0]['id'] === (int) $page->id && $usage['entities'][0]['in'] === 'live', (string) json_encode($usage));
            });

        $page->update(['blocks' => []]);

        $this->agent('delete', ['slug' => 'quote', 'dry_run' => true])->assertOk()->assertSee('would_delete');
        $this->assertTrue(Block::query()->where('slug', 'quote')->exists());

        $this->agent('delete', ['slug' => 'quote'])->assertOk();
        $this->assertFalse(Block::query()->where('slug', 'quote')->exists());
    }

    #[Test]
    public function a_type_called_by_another_is_not_deleted(): void
    {
        $this->publish('card', '<div data-wx-block="card">{{ $title }}</div>', ['kind' => Block::KIND_COMPONENT]);
        $this->publish('grid', '<div data-wx-block="grid"><x-webx-block type="card" title="A" /></div>');

        $this->agent('delete', ['slug' => 'card'])->assertHasErrors(['other types call it — grid']);
    }

    #[Test]
    public function the_history_of_a_type_is_listed_and_an_old_version_comes_back_as_a_draft(): void
    {
        $block = $this->publish('hello', '<p data-wx-block="hello">One</p>');
        $block->saveVersion(['template' => '<p data-wx-block="hello">Two</p>']);

        $this->agent('versions', ['slug' => 'hello'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $history = $json->etc()->toArray();
                self::assertTrue($history['draft'] === 2 && $history['published'] === 1
                    && $history['versions'][0]['number'] === 2 && $history['versions'][0]['is_draft'] === true
                    && $history['versions'][1]['is_published'] === true, (string) json_encode($history));
            });

        $this->agent('version_restore', ['slug' => 'hello', 'number' => 1])->assertOk()->assertSee('"draft":3');

        $this->assertSame('<p data-wx-block="hello">One</p>', $block->refresh()->draftVersion?->template);
        $this->agent('version_restore', ['slug' => 'hello', 'number' => 9])->assertHasErrors(['no version 9']);
    }

    #[Test]
    public function an_update_answers_short_and_says_what_the_panel_would(): void
    {
        $this->publish('hello', '<p data-wx-block="hello">{{ $words }}</p>');

        $this->agent('update', ['slug' => 'hello', 'template' => '<p data-wx-block="hello">{{ $nobody }} @if (</p>'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $answer = $json->etc()->toArray();
                $codes = array_column($answer['warnings'], 'code');
                self::assertTrue(! isset($answer['content']) && in_array('variables-missing', $codes, true)
                    && in_array('syntax', $codes, true), (string) json_encode($answer));
            });

        $this->agent('update', ['slug' => 'hello', 'schema' => [['id' => 'words', 'type' => 'wx-nonexistent']]])
            ->assertOk()
            ->assertSee('unknown-field-type');

        $this->agent('update', ['slug' => 'hello', 'schema' => [['id' => 'bad id', 'type' => 'wx-input']]])
            ->assertHasErrors(['bad id']);
    }

    #[Test]
    public function a_regions_draft_is_discarded_its_history_read_and_restored(): void
    {
        $this->publish('bar', '<nav data-wx-block="bar">{{ $text }}</nav>');

        $this->region('header', [$this->node('bar', ['text' => 'One'], 'b1')]);
        $this->region('header', [$this->node('bar', ['text' => 'Two'], 'b1')]);

        $this->agent('region_versions', ['name' => 'header'], ['blocks.regions'])->assertOk()->assertSee('"number":2');

        $this->agent('region_restore', ['name' => 'header', 'number' => 1], ['blocks.regions'])->assertOk();
        $this->assertSame('One', Region::query()->where('name', 'header')->firstOrFail()->editingTree()[0]['values']['text']);

        $this->agent('region_discard', ['name' => 'header', 'dry_run' => true], ['blocks.regions'])->assertOk()->assertSee('"has_draft":true');
        $this->agent('region_discard', ['name' => 'header'], ['blocks.regions'])->assertOk();
        $this->assertSame('Two', Region::query()->where('name', 'header')->firstOrFail()->editingTree()[0]['values']['text']);
    }

    #[Test]
    public function a_region_is_adopted_from_its_fallback_with_the_rights_the_panel_asks(): void
    {
        $this->tag();

        $this->agent('region_adopt', ['name' => 'header'], ['blocks.regions'])->assertHasErrors(['blocks.manage']);

        $this->agent('region_adopt', ['name' => 'header'], ['blocks.regions', 'blocks.manage'])->assertOk()->assertSee('site-header');

        $this->assertTrue(Block::query()->where('slug', 'site-header')->exists());
        $this->assertSame('site-header', Region::query()->where('name', 'header')->firstOrFail()->editingTree()[0]['type']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $permissions
     */
    private function agent(string $tool, array $arguments, array $permissions = ['blocks.view', 'blocks.manage']): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return WebxServer::actingAs($this->as($permissions), 'cms')->tool($bound, $arguments);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function as(array $permissions): CmsUser
    {
        return $this->editor($permissions);
    }
}
