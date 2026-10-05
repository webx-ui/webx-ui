<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Press\Models\Outlet;

/**
 * The section in the panel (§4.9, §4.10): its entry in the manifest, the permissions, the list in
 * the shape the panel reads, the order and the bin.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_section_is_one_entry_with_its_icon(): void
    {
        /** @var list<array<string, mixed>> $modules */
        $modules = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');
        $press = array_values(array_filter($modules, static fn (array $module): bool => ($module['id'] ?? null) === 'press'))[0] ?? null;

        $this->assertIsArray($press);
        $this->assertSame('Press', $press['title']);
        $this->assertSame('newspaper', $press['icon']);
        $this->assertNull($press['group'] ?? null);
    }

    #[Test]
    public function viewing_and_managing_are_two_permissions(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('One')]);
        $viewer = $this->editor(['press.view']);

        $this->getJson($this->api())->assertUnauthorized();

        $this->actingAs($viewer, 'cms')->getJson($this->api())->assertOk();
        $this->actingAs($viewer, 'cms')->getJson($this->api($outlet->id))->assertOk();
        $this->actingAs($viewer, 'cms')->postJson($this->api(), ['values' => ['title' => ['en' => 'Vogue']]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->putJson($this->api($outlet->id), ['values' => ['published' => false]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->deleteJson($this->api($outlet->id))->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('reorder'), ['ids' => [$outlet->id]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api($outlet->id.'/restore'))->assertForbidden();
    }

    #[Test]
    public function the_list_says_where_each_outlet_is_seen(): void
    {
        $logo = $this->file('media/ab/cd/tatler.png', 'image/png');

        $this->outlet('Tatler', [$this->row('One', ['title' => ['en' => 'One', 'ru' => 'Один']]), $this->row('Two')], values: ['logo' => ['path' => $logo->path], 'featured' => true]);
        $this->outlet('Nowhere', [$this->row('Hidden', ['is_hidden' => true])]);
        $this->outlet('Empty', published: false);

        $rows = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk()->json('data');

        $this->assertSame(['Tatler', 'Nowhere', 'Empty'], array_column($rows, 'title'));
        $this->assertSame(
            ['id', 'title', 'logo', 'published', 'featured', 'position', 'locales', 'articles_count', 'updated_at', 'deleted_at'],
            array_keys($rows[0]),
        );
        $this->assertSame(['en', 'ru'], $rows[0]['locales']);
        $this->assertSame(2, $rows[0]['articles_count']);
        // An address of the disk, not the panel's preview route: the same value reaches the site,
        // where that route answers every visitor with a 401.
        $this->assertStringNotContainsString('/thumb', $rows[0]['logo']['thumb']);
        $this->assertStringContainsString('tatler', $rows[0]['logo']['thumb']);
        $this->assertTrue($rows[0]['featured']);

        // Published, with an article, and seen in no language: what the list warns about.
        $this->assertTrue($rows[1]['published']);
        $this->assertSame([], $rows[1]['locales']);
        $this->assertSame(1, $rows[1]['articles_count']);
        $this->assertNull($rows[2]['logo']);
    }

    #[Test]
    public function a_search_finds_an_outlet_by_its_name_its_site_or_an_article(): void
    {
        $this->outlet('Tatler', [$this->row('The clinic of the year')], values: ['website_url' => 'https://tatler.example']);
        $this->outlet('Vogue', [$this->row('Something else')]);

        $editor = $this->editor();

        foreach (['vog' => ['Vogue'], 'tatler.example' => ['Tatler'], 'clinic' => ['Tatler']] as $term => $expected) {
            $this->assertSame($expected, array_column($this->actingAs($editor, 'cms')->getJson($this->api().'?search='.$term)->json('data'), 'title'));
        }
    }

    #[Test]
    public function the_order_is_dragged_and_a_new_one_goes_last(): void
    {
        $a = $this->outlet('A');
        $b = $this->outlet('B');
        $c = $this->outlet('C');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api('reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertNoContent();

        $this->assertSame(['C', 'A', 'B'], array_column($this->actingAs($editor, 'cms')->getJson($this->api())->json('data'), 'title'));

        $this->outlet('D');
        $this->assertSame('D', Outlet::query()->orderByDesc('position')->first()?->displayTitle());
    }

    #[Test]
    public function the_bin_keeps_the_articles_and_gives_the_address_back(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('One')]);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api($outlet->id))->assertNoContent();
        $this->get('/press/tatler')->assertNotFound();

        $this->assertSame(['Tatler'], array_column($this->actingAs($editor, 'cms')->getJson($this->api().'?trashed=1')->json('data'), 'title'));
        $this->assertSame([], $this->actingAs($editor, 'cms')->getJson($this->api())->json('data'));

        $this->actingAs($editor, 'cms')
            ->postJson($this->api($outlet->id.'/restore'))
            ->assertOk()
            ->assertJsonPath('data.title', 'Tatler')
            ->assertJsonPath('data.articles_count', 1)
            ->assertJsonPath('data.deleted_at', null);

        $this->get('/press/tatler')->assertOk()->assertSee('One');
    }
}
