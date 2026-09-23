<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Models\Route;
use WebxUi\Services\Models\Service;

/**
 * The section as the panel reaches it (§4.6, §4.7): the manifest, the permissions, the list with
 * its two orders, and what a row can do — publish, take off, bin, bring back.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_two_sections_are_in_the_manifest_under_one_group(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        /** @var array<int, array<string, mixed>> $modules */
        $modules = $response->json('data.modules');
        $ours = [];

        foreach ($modules as $module) {
            if (($module['group'] ?? null) === 'services') {
                $ours[(string) $module['id']] = $module;
            }
        }

        $this->assertSame(['services', 'service-categories'], array_keys($ours));
        $this->assertSame(['services.view', 'services.manage'], $ours['services']['permissions']);
        $this->assertSame(['services.categories.manage'], $ours['service-categories']['permissions']);

        // A group nobody declared drops its entries to the top level, and one without an icon
        // falls back to the gear of the System group.
        $icons = array_column((array) $response->json('data.groups'), 'icon', 'id');
        $this->assertSame('star', $icons['services'] ?? null);
    }

    #[Test]
    public function a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $service = $this->service('implants');

        $this->getJson($this->api())->assertUnauthorized();

        $viewer = $this->editor(['services.view']);

        $this->actingAs($viewer, 'cms')->getJson($this->api())->assertOk();
        $this->actingAs($viewer, 'cms')->postJson($this->api(), ['title' => 'Crowns'])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('reorder'), ['ids' => [$service->getKey()]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('categories'), ['title' => 'Surgery'])->assertForbidden();

        // The categories are read by anybody who may open a service: the form files into them.
        $this->actingAs($viewer, 'cms')->getJson($this->api('categories'))->assertOk();
    }

    #[Test]
    public function the_list_is_the_whole_catalogue_in_its_order_with_the_categories_beside_it(): void
    {
        $surgery = $this->category('surgery');
        [$a, $b, $c] = [$this->service('a'), $this->service('b'), $this->service('c')];
        $a->syncCategories([$surgery->getKey()]);

        DB::table('services')->where('id', $a->getKey())->update(['position' => 30]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame([$b->getKey(), $c->getKey(), $a->getKey()], array_column((array) $response->json('data'), 'id'));
        $this->assertNull($response->json('meta'));
        $this->assertSame('services/a', $response->json('data.2.path'));
        $this->assertSame([['id' => $surgery->getKey(), 'title' => 'Surgery', 'slug' => 'surgery']], $response->json('data.2.categories'));
        $this->assertSame([['id' => $surgery->getKey(), 'title' => 'Surgery']], $response->json('filters.categories'));
    }

    #[Test]
    public function narrowed_to_a_category_the_list_comes_in_that_categorys_own_order(): void
    {
        $surgery = $this->category('surgery');
        $other = $this->category('other');
        [$a, $b, $c] = [$this->service('a'), $this->service('b'), $this->service('c')];

        foreach ([$a, $b] as $service) {
            $service->syncCategories([$surgery->getKey(), $other->getKey()]);
        }

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$b->getKey(), $a->getKey()], 'category' => $surgery->getKey()])
            ->assertNoContent();

        $in = fn (int $category): array => array_column((array) $this->actingAs($editor, 'cms')
            ->getJson($this->api().'?category='.$category)->json('data'), 'id');

        $this->assertSame([$b->getKey(), $a->getKey()], $in($surgery->getKey()));
        // Only that category moved: the other and the whole list keep their own.
        $this->assertSame([$a->getKey(), $b->getKey()], $in($other->getKey()));
        $this->assertSame(
            [$a->getKey(), $b->getKey(), $c->getKey()],
            array_column((array) $this->actingAs($editor, 'cms')->getJson($this->api())->json('data'), 'id'),
        );
    }

    #[Test]
    public function dragging_without_a_category_writes_the_whole_list(): void
    {
        [$a, $b, $c] = [$this->service('a'), $this->service('b'), $this->service('c')];

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$c->getKey(), $a->getKey(), $b->getKey()]])
            ->assertNoContent();

        $this->assertSame([$c->getKey(), $a->getKey(), $b->getKey()], Service::query()->orderedIn()->pluck('id')->all());
    }

    #[Test]
    public function the_list_narrows_by_state_and_by_words(): void
    {
        $live = $this->service('implants');
        $draft = $this->service('crowns', published: false);
        $modified = $this->service('whitening');
        $modified->saveDraft(['title' => ['en' => 'Whitening, new']]);
        $pulled = $this->service('veneers');
        $pulled->unpublish();

        $editor = $this->editor();
        $ids = fn (string $query): array => array_column((array) $this->actingAs($editor, 'cms')
            ->getJson($this->api().'?'.$query)->json('data'), 'id');

        $this->assertSame([$live->getKey()], $ids('status=published'));
        $this->assertSame([$draft->getKey()], $ids('status=draft'));
        $this->assertSame([$modified->getKey()], $ids('status=modified'));
        $this->assertSame([$pulled->getKey()], $ids('status=unpublished'));
        $this->assertSame([$modified->getKey()], $ids('q=whiten'));
    }

    #[Test]
    public function a_new_service_is_a_draft_whose_address_is_made_of_its_title(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'Dental implants'])
            ->assertCreated();

        $this->assertSame('dental-implants', $response->json('data.slug'));
        $this->assertSame('draft', $response->json('data.status'));
        $this->assertSame('services/dental-implants', $response->json('data.path'));
    }

    #[Test]
    public function a_service_cannot_take_the_address_of_a_category_and_says_whose_it_is(): void
    {
        $this->category('implants')->forceFill(['title' => ['en' => 'Implantology']])->save();

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'Implants'])
            ->assertUnprocessable();

        $this->assertStringContainsString('Implantology', (string) json_encode($response->json('errors')));
    }

    #[Test]
    public function a_row_publishes_takes_off_bins_and_brings_back(): void
    {
        $service = $this->service('implants', published: false);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api($service->getKey().'/publish'))
            ->assertOk()->assertJsonPath('data.status', 'published');

        $this->actingAs($editor, 'cms')->postJson($this->api($service->getKey().'/unpublish'))
            ->assertOk()->assertJsonPath('data.status', 'unpublished');

        $this->actingAs($editor, 'cms')->deleteJson($this->api($service->getKey()))->assertNoContent();
        $this->assertSame(0, Route::query()->where('path', 'services/implants')->count());

        $bin = $this->actingAs($editor, 'cms')->getJson($this->api().'?trashed=1')->assertOk();
        $this->assertSame([$service->getKey()], array_column((array) $bin->json('data'), 'id'));

        $this->actingAs($editor, 'cms')->postJson($this->api($service->getKey().'/restore'))
            ->assertOk()->assertJsonPath('data.path', 'services/implants');
    }
}
