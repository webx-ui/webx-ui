<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;

/**
 * "Site regions" in the panel (§7 of the regions spec): the list, the editor's draft with its
 * revision, publication that refuses a failing block, taking it off, the history, and moving the
 * fallback into a block type.
 */
final class RegionsPanelTest extends RegionTestCase
{
    #[Test]
    public function the_section_is_for_whoever_holds_the_regions_permission(): void
    {
        $this->getJson($this->api())->assertStatus(401);
        $this->actingAs($this->editor(['blocks.view', 'blocks.manage']), 'cms')->getJson($this->api())->assertForbidden();

        $this->actingAs($this->editor(['blocks.regions']), 'cms')->getJson($this->api())->assertOk();
    }

    #[Test]
    public function the_list_names_every_declared_region_saved_or_not(): void
    {
        $this->publish('bar', '<div>{{ $text }}</div>');
        $this->region('header', [$this->node('bar', ['text' => 'A']), ['key' => 'off', 'type' => 'bar', 'hidden' => true, 'values' => []]]);

        $rows = $this->actingAs($this->editor(['blocks.regions']), 'cms')->getJson($this->api())->assertOk()->json('data');

        $this->assertSame(['header', 'footer'], array_column($rows, 'name'));

        $this->assertSame('Header', $rows[0]['title']);
        $this->assertSame('Top of every page.', $rows[0]['description']);
        $this->assertTrue($rows[0]['published']);
        $this->assertFalse($rows[0]['has_draft']);
        $this->assertSame(1, $rows[0]['count']);
        $this->assertIsInt($rows[0]['id']);

        $this->assertNull($rows[1]['id']);
        $this->assertSame('Footer', $rows[1]['title']);
        $this->assertSame(2, $rows[1]['max']);
        $this->assertFalse($rows[1]['published']);
        $this->assertNull($rows[1]['published_at']);
        $this->assertSame(0, $rows[1]['count']);
    }

    #[Test]
    public function a_region_the_config_does_not_declare_is_not_there(): void
    {
        $this->actingAs($this->editor(['blocks.regions']), 'cms')->getJson($this->api('sidebar'))->assertNotFound();
    }

    #[Test]
    public function the_editor_saves_a_draft_and_a_stale_revision_is_refused(): void
    {
        $this->publish('bar', '<div>{{ $text }}</div>');
        $editor = $this->editor(['blocks.regions']);

        $opened = $this->actingAs($editor, 'cms')->getJson($this->api('footer'))->assertOk()->json('data');

        $this->assertSame([], $opened['blocks']);
        $this->assertSame(Content::revision([]), $opened['revision']);
        $this->assertStringContainsString('/_preview/region/footer?token=', $opened['preview_url']);
        $this->assertFalse($opened['can_adopt']);

        $tree = [$this->node('bar', ['text' => 'First'], 'k1')];

        $saved = $this->actingAs($editor, 'cms')
            ->putJson($this->api('footer'), ['blocks' => $tree, 'revision' => $opened['revision']])
            ->assertOk()
            ->json('data');

        // The first save makes the row.
        $this->assertIsInt($saved['id']);
        $this->assertTrue($saved['has_draft']);
        $this->assertFalse($saved['published']);
        $this->assertSame('First', $saved['blocks'][0]['values']['text']);
        $this->assertSame(Content::revision($saved['blocks']), $saved['revision']);

        // Somebody who opened it before that save.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api('footer'), ['blocks' => [], 'revision' => $opened['revision']])
            ->assertStatus(409)
            ->assertJsonPath('revision', $saved['revision']);
    }

    #[Test]
    public function a_region_takes_only_what_its_declaration_and_the_types_allow(): void
    {
        $this->publish('bar', '<div>{{ $text }}</div>');
        $this->publish('shop-header', '<header>{{ $text }}</header>', ['allowed_in' => ['region:header']]);
        $editor = $this->editor(['blocks.regions']);

        // `max: 2` on the footer.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api('footer'), ['blocks' => [$this->node('bar'), $this->node('bar'), $this->node('bar')]])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['blocks']]);

        // A type meant for the header, in the footer.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api('footer'), ['blocks' => [$this->node('shop-header')]])
            ->assertUnprocessable();

        // And in the header it is at home.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api('header'), ['blocks' => [$this->node('shop-header')]])
            ->assertOk();
    }

    #[Test]
    public function publishing_puts_the_draft_on_the_site_and_refuses_a_failing_block(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->publish('bomb', '<p>@if ($boom) {{ throw new RuntimeException(\'Boom\') }} @endif fine</p>');
        $editor = $this->editor(['blocks.regions']);

        // Never saved: nothing to publish.
        $this->actingAs($editor, 'cms')->postJson($this->api('header/publish'))->assertUnprocessable();

        $this->actingAs($editor, 'cms')->putJson($this->api('header'), ['blocks' => [$this->node('bomb', ['boom' => true], 'x1')]])->assertOk();

        $refused = $this->actingAs($editor, 'cms')->postJson($this->api('header/publish'))->assertUnprocessable();
        $this->assertStringContainsString('bomb', (string) $refused->json('errors.blocks.0'));
        $this->assertStringContainsString('Boom', (string) $refused->json('errors.blocks.0'));
        $this->assertStringContainsString('Header from code', $this->tag());

        $this->actingAs($editor, 'cms')->putJson($this->api('header'), ['blocks' => [$this->node('bar', ['text' => 'Live'], 'k1')]])->assertOk();

        $published = $this->actingAs($editor, 'cms')->postJson($this->api('header/publish'))->assertOk()->json('data');

        $this->assertTrue($published['published']);
        $this->assertFalse($published['has_draft']);
        $this->assertNotNull($published['published_at']);
        $this->assertStringContainsString('<div class="b-bar">Live</div>', $this->tag());

        // Off the site: the header from code again, the blocks kept for next time.
        $off = $this->actingAs($editor, 'cms')->postJson($this->api('header/unpublish'))->assertOk()->json('data');

        $this->assertFalse($off['published']);
        $this->assertSame('Live', $off['blocks'][0]['values']['text']);
        $this->assertStringContainsString('Header from code', $this->tag());
    }

    #[Test]
    public function the_history_lists_publications_and_restores_one_into_the_draft(): void
    {
        $this->publish('bar', '<div>{{ $text }}</div>');
        $editor = $this->editor(['blocks.regions']);

        foreach (['One', 'Two'] as $text) {
            $this->actingAs($editor, 'cms')->putJson($this->api('header'), ['blocks' => [$this->node('bar', ['text' => $text], 'k1')]])->assertOk();
            $this->actingAs($editor, 'cms')->postJson($this->api('header/publish'))->assertOk();
        }

        $versions = $this->actingAs($editor, 'cms')->getJson($this->api('header/versions'))->assertOk()->json('data');

        $this->assertSame([2, 1], array_column($versions, 'number'));
        $this->assertSame(['id' => $editor->id, 'name' => 'Editor'], $versions[0]['author']);
        $this->assertSame('panel', $versions[0]['source']);

        $restored = $this->actingAs($editor, 'cms')->postJson($this->api('header/versions/1/restore'))->assertOk()->json('data');

        $this->assertTrue($restored['has_draft']);
        $this->assertSame('One', $restored['blocks'][0]['values']['text']);
        // The site still shows the second until somebody publishes.
        $this->assertStringContainsString('Two', $this->tag());

        $this->actingAs($editor, 'cms')->postJson($this->api('header/versions/9/restore'))->assertNotFound();

        // And the draft can be thrown away.
        $discarded = $this->actingAs($editor, 'cms')->deleteJson($this->api('header/draft'))->assertOk()->json('data');
        $this->assertFalse($discarded['has_draft']);
        $this->assertSame('Two', $discarded['blocks'][0]['values']['text']);
    }

    #[Test]
    public function the_markup_from_code_moves_into_a_block_type_of_its_own(): void
    {
        $regions = $this->editor(['blocks.regions']);
        $both = $this->editor(['blocks.regions', 'blocks.manage']);

        // The fallback is only known once the layout has printed the tag.
        $this->actingAs($both, 'cms')->postJson($this->api('header/adopt'))->assertUnprocessable();

        $this->tag();

        $this->actingAs($regions, 'cms')->postJson($this->api('header/adopt'))->assertForbidden();
        $this->assertTrue($this->actingAs($both, 'cms')->getJson($this->api('header'))->json('data.can_adopt'));

        $adopted = $this->actingAs($both, 'cms')->postJson($this->api('header/adopt'))->assertCreated();

        $this->assertSame('site-header', $adopted->json('block.slug'));
        $this->assertSame('site-header', $adopted->json('data.blocks.0.type'));
        $this->assertFalse($adopted->json('data.can_adopt'));

        $block = Block::query()->where('slug', 'site-header')->firstOrFail();
        $this->assertSame(['region:header'], $block->allowed_in);
        $this->assertNotNull($block->published_version_id);
        $this->assertStringContainsString('Header from code', (string) $block->publishedVersion?->template);

        $this->actingAs($both, 'cms')->postJson($this->api('header/adopt'))->assertStatus(409);
    }

    #[Test]
    public function the_catalogue_and_the_render_of_one_block_are_open_to_the_region_editor(): void
    {
        $bar = $this->publish('bar', '<div>{{ $text }}</div>');
        $this->publish('shop-header', '<header>{{ $text }}</header>', ['allowed_in' => ['region:header']]);
        $this->publish('page-only', '<p>{{ $text }}</p>', ['allowed_in' => ['root']]);
        $editor = $this->editor(['blocks.regions']);

        $all = $this->actingAs($editor, 'cms')->getJson('/api/cms/blocks/catalog')->assertOk()->json('data');
        $this->assertCount(3, $all);

        $footer = $this->actingAs($editor, 'cms')->getJson('/api/cms/blocks/catalog?region=footer')->assertOk()->json('data');
        $this->assertSame(['bar'], array_column($footer, 'slug'));

        $header = $this->actingAs($editor, 'cms')->getJson('/api/cms/blocks/catalog?region=header')->assertOk()->json('data');
        $this->assertSame(['bar', 'shop-header'], array_column($header, 'slug'));

        $this->actingAs($editor, 'cms')->postJson("/api/cms/blocks/{$bar->id}/render", ['values' => ['text' => 'Hi']])->assertOk();

        // Nothing else of the section.
        $this->actingAs($editor, 'cms')->getJson('/api/cms/blocks')->assertForbidden();
    }

    #[Test]
    public function a_type_is_offered_only_in_a_region_by_its_allowed_in(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson('/api/cms/blocks', [
            'slug' => 'shop-header',
            'title' => 'Shop header',
            'allowed_in' => ['region:header', 'root'],
        ])->assertCreated();

        $this->actingAs($this->editor(), 'cms')->postJson('/api/cms/blocks', [
            'slug' => 'odd',
            'title' => 'Odd',
            'allowed_in' => ['zone:header'],
        ])->assertUnprocessable();
    }

    #[Test]
    public function where_a_type_stands_names_the_regions_and_its_publication_checks_their_values(): void
    {
        $bar = $this->publish('bar', '<div>{{ $text }}</div>');
        $this->region('header', [$this->node('bar', ['text' => 'Menu'])]);

        $usage = $this->actingAs($this->editor(), 'cms')->getJson("/api/cms/blocks/{$bar->id}/usage")->assertOk()->json('data');

        $this->assertSame([Region::class], array_column($usage, 'model'));
        $this->assertSame(['Region “Header”'], array_column($usage, 'title'));

        // A new version that only breaks on the region's values is refused.
        $bar->saveVersion(['template' => '<div>{{ $text === \'Menu\' ? throw new RuntimeException(\'no menus\') : $text }}</div>']);

        $this->actingAs($this->editor(), 'cms')->postJson("/api/cms/blocks/{$bar->id}/publish")->assertUnprocessable();
    }

    private function api(string $path = ''): string
    {
        return rtrim('/api/cms/regions/'.$path, '/');
    }
}
