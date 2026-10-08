<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A region to an agent (§8 of the regions spec): an entity the content tools know without the
 * site listing it, whose first write makes its row, plus a list, a publication and a way back.
 */
final class RegionsMcpTest extends RegionTestCase
{
    #[Test]
    public function a_region_is_an_entity_whatever_the_site_listed(): void
    {
        $this->app['config']->set('webx-blocks.entities', []);

        $description = $this->app->make(ToolRegistry::class)->tool('blocks_get_content')->tool->inputSchema['properties']['entity']['description'];
        $this->assertStringContainsString('region', $description);

        $this->agent('get_content', ['entity' => 'region', 'id' => 'header'])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['entity'] === 'region' && $content['id'] === 'header' && $content['title'] === 'Header'
                    && $content['published'] === false && $content['live'] === [] && $content['draft'] === null, (string) json_encode($content));
            });

        $this->agent('get_content', ['entity' => 'region', 'id' => 'sidebar'])->assertHasErrors(['No region is called [sidebar]']);

        // Reading makes no row.
        $this->assertSame(0, Region::query()->count());
    }

    #[Test]
    public function the_first_write_makes_the_row_and_the_revision_guards_it(): void
    {
        $this->publish('bar', '<div>{{ $text }}</div>');
        $editor = $this->editor(['blocks.regions']);

        $revision = null;

        $this->agent('set_content', ['force' => true, 'entity' => 'region', 'id' => 'header', 'blocks' => [['type' => 'bar', 'values' => ['text' => 'First']]]], $editor)
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json) use (&$revision): void {
                $content = $json->etc()->toArray();
                $revision = $content['revision'];
                self::assertTrue($content['written'] === 'draft' && str_contains((string) $content['preview_url'], '/_preview/region/header?token='), (string) json_encode($content));
            });

        $region = Region::query()->where('name', 'header')->firstOrFail();
        $this->assertFalse($region->isPublished());
        $this->assertSame('First', $region->editingTree()[0]['values']['text']);

        $this->agent('edit_content', ['entity' => 'region', 'id' => 'header', 'revision' => 'stale', 'ops' => [['op' => 'add', 'type' => 'bar']]], $editor)
            ->assertHasErrors(['changed since you read it']);

        $this->agent('edit_content', ['entity' => 'region', 'id' => 'header', 'revision' => $revision, 'ops' => [['op' => 'add', 'type' => 'bar', 'values' => ['text' => 'Second']]]], $editor)
            ->assertOk();

        $this->assertCount(2, $region->refresh()->editingTree());

        // The footer holds two at most.
        $this->agent('set_content', ['force' => true, 'entity' => 'region', 'id' => 'footer', 'blocks' => [['type' => 'bar'], ['type' => 'bar'], ['type' => 'bar']]], $editor)
            ->assertHasErrors(['at most 2']);
    }

    #[Test]
    public function the_region_permission_is_what_a_region_asks_for(): void
    {
        $this->app['config']->set('webx-blocks.entities', [Page::class]);
        $this->publish('bar', '<div>{{ $text }}</div>');
        $page = Page::query()->create(['title' => 'Page']);

        $regionsOnly = $this->editor(['blocks.regions']);
        $blocksOnly = $this->editor(['blocks.view', 'blocks.manage']);

        $this->agent('set_content', ['force' => true, 'entity' => 'region', 'id' => 'header', 'blocks' => []], $blocksOnly)->assertHasErrors(['blocks.regions']);
        $this->agent('set_content', ['force' => true, 'entity' => 'note', 'id' => $page->id, 'blocks' => []], $regionsOnly)->assertHasErrors(['blocks.manage']);

        $this->agent('set_content', ['force' => true, 'entity' => 'region', 'id' => 'header', 'blocks' => []], $regionsOnly)->assertOk();
    }

    #[Test]
    public function publishing_a_region_can_be_tried_first_and_taken_back(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->publish('bomb', '<p>@if ($boom) {{ throw new RuntimeException(\'Boom\') }} @endif fine</p>');
        $editor = $this->editor(['blocks.regions']);

        $this->agent('region_publish', ['name' => 'header'], $editor)->assertHasErrors(['never saved']);

        $this->agent('set_content', ['force' => true, 'entity' => 'region', 'id' => 'header', 'blocks' => [['type' => 'bomb', 'values' => ['boom' => 'yes']]]], $editor)->assertOk();
        $this->agent('region_publish', ['name' => 'header'], $editor)->assertHasErrors(['Not published', 'Boom']);
        $this->agent('region_publish', ['name' => 'header', 'dry_run' => true], $editor)->assertHasErrors(['Would not publish']);

        $this->agent('set_content', ['force' => true, 'entity' => 'region', 'id' => 'header', 'blocks' => [['type' => 'bar', 'values' => ['text' => 'Live']]]], $editor)->assertOk();

        $this->tag();

        $this->agent('region_publish', ['name' => 'header', 'dry_run' => true], $editor)
            ->assertOk()
            ->assertSee('region-site::header');

        $this->assertFalse(Region::query()->where('name', 'header')->firstOrFail()->isPublished());

        $this->agent('region_publish', ['name' => 'header'], $editor)->assertOk();
        $this->assertStringContainsString('Live', $this->tag());

        $this->agent('regions', [], $editor)
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $content = $json->etc()->toArray();
                self::assertTrue($content['count'] === 2
                    && $content['regions'][0]['name'] === 'header'
                    && $content['regions'][0]['state'] === 'published'
                    && $content['regions'][0]['fallback'] === 'region-site::header'
                    && $content['regions'][1]['state'] === 'never saved', (string) json_encode($content));
            });

        $this->agent('region_unpublish', ['name' => 'header', 'dry_run' => true], $editor)->assertOk();
        $this->assertTrue(Region::query()->where('name', 'header')->firstOrFail()->isPublished());

        $this->agent('region_unpublish', ['name' => 'header'], $editor)->assertOk();
        $this->assertStringContainsString('Header from code', $this->tag());
    }

    #[Test]
    public function the_preview_link_of_a_region_names_the_page_it_is_drawn_on(): void
    {
        $this->agent('preview_url', ['entity' => 'region', 'id' => 'header', 'at' => '/about'], $this->editor(['blocks.regions']))
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $url = (string) $json->etc()->toArray()['url'];
                self::assertTrue(str_contains($url, '/_preview/region/header?') && str_contains($url, 'at=%2Fabout'), $url);
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return $as instanceof CmsUser
            ? WebxServer::actingAs($as, 'cms')->tool($bound, $arguments)
            : WebxServer::tool($bound, $arguments);
    }
}
