<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;

/**
 * The places through the panel's API (§5.6): the declared ones and somebody's own, the lazy row,
 * and what may not be done to a declared place or a full one.
 */
final class PlacesTest extends TestCase
{
    #[Test]
    public function the_list_is_the_declared_places_first_then_the_own_ones_by_name(): void
    {
        $this->picture();
        $this->banner('hero');
        $this->banner('hero')->delete();
        Place::query()->create(['key' => 'zeta', 'title' => ['en' => 'Aardvark']]);
        Place::query()->create(['key' => 'alpha', 'title' => ['en' => 'Zebra']]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api('places'))->assertOk();

        $this->assertSame(['hero', 'promo', 'notice', 'zeta', 'alpha'], array_column((array) $response->json('data'), 'key'));

        $hero = (array) $response->json('data.0');
        $this->assertSame(['id', 'key', 'title', 'declared', 'layout', 'count'], array_keys($hero));
        $this->assertIsInt($hero['id']);
        $this->assertSame('Home page slider', $hero['title']);
        $this->assertTrue($hero['declared']);
        $this->assertSame('slider', $hero['layout']);
        $this->assertSame(1, $hero['count'], 'the bin does not count in the list');

        $promo = (array) $response->json('data.1');
        $this->assertNull($promo['id'], 'declared, and nothing saved into it yet');
        $this->assertSame('single', $promo['layout']);
        $this->assertSame(0, $promo['count']);

        $zeta = (array) $response->json('data.3');
        $this->assertFalse($zeta['declared']);
        $this->assertSame('Aardvark', $zeta['title']);
        $this->assertSame('slider', $zeta['layout']);

        $inRussian = $this->actingAs($this->editor(), 'cms')->getJson($this->api('places'), ['X-Webx-Locale' => 'ru'])->assertOk();
        $this->assertSame('Слайдер на главной', $inRussian->json('data.0.title'));
        $this->assertSame('Aardvark', $inRussian->json('data.3.title'), 'not in Russian — the default language');
    }

    #[Test]
    public function a_declared_place_gets_its_row_with_its_first_banner(): void
    {
        $this->picture();
        $this->assertSame(0, Place::query()->count());

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('places/promo/banners'), ['values' => ['image' => ['path' => 'media/ab/cd/wide.jpg']]])
            ->assertCreated()
            ->assertJsonPath('data.banner.place', 'promo');

        $this->assertSame(['promo'], Place::query()->pluck('key')->all());
        $this->assertNull(Place::query()->firstOrFail()->getTranslations('title') ?: null, 'a declared place is named by the config');
    }

    #[Test]
    public function a_refused_first_banner_leaves_no_row_behind(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('places/promo/banners'), ['values' => ['title' => ['en' => 'No picture']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');

        // Past the form's own checks and refused by the screen, inside the save.
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('places/promo/banners'), ['values' => ['image' => ['path' => 'media/ab/cd/clip.mp4']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertSame(0, Place::query()->count());
        $this->assertSame(0, Banner::query()->withTrashed()->count());
    }

    /**
     * `notice` is a place of words only (`'image' => false`): the announcement bar of the widgets
     * prints its title, text and buttons. Everywhere else a picture is still required.
     */
    #[Test]
    public function a_place_of_words_only_takes_a_banner_without_a_picture(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('places/notice/banners'), ['values' => ['title' => ['en' => 'Open on Saturdays'], 'enabled' => true]])
            ->assertCreated()
            ->assertJsonPath('data.banner.place', 'notice');

        $card = banners('notice')->first();
        $this->assertIsArray($card);
        $this->assertSame('Open on Saturdays', $card['title']);
        $this->assertNull($card['image']);

        // A video still needs its poster.
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('places/notice/banners'), ['values' => ['title' => ['en' => 'Clip'], 'video' => ['path' => 'media/ab/cd/clip.mp4']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        // Moved to a place that stands on pictures, it is refused there.
        $id = (int) Banner::query()->value('id');
        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($id), ['values' => [], 'place' => 'hero'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        // A banner of a picture place whose picture is gone is still left out.
        $this->banner('hero', attributes: ['image' => ['path' => 'media/ab/cd/gone.jpg']]);
        $this->assertSame([], banners('hero')->get());
    }

    #[Test]
    public function an_own_place_is_made_renamed_and_deleted_when_empty(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('places'), ['key' => 'sidebar', 'title' => ['en' => 'Sidebar', 'ru' => 'Сайдбар']])
            ->assertCreated()
            ->assertJsonPath('data.key', 'sidebar')
            ->assertJsonPath('data.title', 'Sidebar')
            ->assertJsonPath('data.declared', false)
            ->assertJsonPath('data.count', 0);

        $this->actingAs($editor, 'cms')
            ->putJson($this->api('places/sidebar'), ['title' => ['en' => 'Side column']])
            ->assertOk()
            ->assertJsonPath('data.title', 'Side column');

        $this->assertSame(['en' => 'Side column', 'ru' => 'Сайдбар'], Place::query()->where('key', 'sidebar')->firstOrFail()->getTranslations('title'));

        $this->actingAs($editor, 'cms')->deleteJson($this->api('places/sidebar'))->assertNoContent();
        $this->assertFalse(Place::query()->where('key', 'sidebar')->exists());
    }

    #[Test]
    public function a_key_is_checked_and_a_taken_one_refused(): void
    {
        $editor = $this->editor();
        Place::query()->create(['key' => 'mine', 'title' => ['en' => 'Mine']]);

        foreach (['', 'Upper', '1st', 'with space', str_repeat('a', 65)] as $key) {
            $this->actingAs($editor, 'cms')
                ->postJson($this->api('places'), ['key' => $key, 'title' => 'Name'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['key']);
        }

        foreach (['hero', 'mine'] as $taken) {
            $this->actingAs($editor, 'cms')
                ->postJson($this->api('places'), ['key' => $taken, 'title' => 'Name'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.key.0', 'This key is taken.');
        }

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('places'), ['key' => 'fine', 'title' => ['ru' => 'Только по-русски']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title.en']);

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('places'), ['key' => 'plain', 'title' => 'A string is the default language'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'A string is the default language');
    }

    #[Test]
    public function a_full_place_is_refused_with_its_count_the_bin_included(): void
    {
        $this->picture();
        $editor = $this->editor();
        Place::query()->create(['key' => 'side', 'title' => ['en' => 'Side']]);
        $this->banner('side');
        $binned = $this->banner('side');
        $binned->delete();

        $this->actingAs($editor, 'cms')
            ->deleteJson($this->api('places/side'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['place'])
            ->assertJsonPath('count', 2)
            ->assertJsonPath('errors.place.0', 'Take the banners out of this place first, the bin included. Banners in it: 2.');

        Banner::query()->where('place_id', $binned->place_id)->whereKeyNot($binned->id)->forceDelete();

        $this->actingAs($editor, 'cms')
            ->deleteJson($this->api('places/side'))
            ->assertUnprocessable()
            ->assertJsonPath('count', 1); // only the bin is left, and it still counts
    }

    #[Test]
    public function a_declared_place_is_neither_renamed_nor_deleted(): void
    {
        $this->picture();
        $editor = $this->editor();
        $this->banner('hero');

        $this->actingAs($editor, 'cms')->putJson($this->api('places/hero'), ['title' => 'Mine now'])->assertForbidden();
        $this->actingAs($editor, 'cms')->deleteJson($this->api('places/promo'))->assertForbidden();
        $this->actingAs($editor, 'cms')->deleteJson($this->api('places/nowhere'))->assertNotFound();
    }

    #[Test]
    public function a_declared_place_taken_out_of_the_config_becomes_an_own_one(): void
    {
        $this->picture();
        $this->banner('promo')->forceDelete();

        config()->set('webx-banners.places', ['hero' => ['title' => 'Hero']]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api('places'))->assertOk();

        $this->assertSame(['hero', 'promo'], array_column((array) $response->json('data'), 'key'));
        $this->assertFalse($response->json('data.1.declared'));
        $this->assertSame('promo', $response->json('data.1.title'), 'no name of its own — its key');

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api('places/promo'))->assertNoContent();
    }

    #[Test]
    public function the_banners_of_a_place_in_their_order_and_the_bin(): void
    {
        $this->picture();
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');
        $a = $this->banner(title: 'A', attributes: ['video' => ['path' => 'media/ab/cd/clip.mp4']]);
        $b = $this->banner(title: null, attributes: ['enabled' => false, 'image' => null]);
        $c = $this->banner(title: 'C');
        $c->delete();

        $editor = $this->editor(['banners.view']);
        $response = $this->actingAs($editor, 'cms')->getJson($this->api('places/hero/banners'))->assertOk();

        $this->assertSame([$a->id, $b->id], array_column((array) $response->json('data'), 'id'));

        $row = (array) $response->json('data.0');
        $this->assertSame(['id', 'title', 'thumb', 'video', 'enabled', 'position', 'updated_at', 'deleted_at'], array_keys($row));
        $this->assertSame('A', $row['title']);
        $this->assertIsString($row['thumb']);
        $this->assertTrue($row['video']);
        $this->assertTrue($row['enabled']);

        $this->assertSame('#'.$b->id, $response->json('data.1.title'));
        $this->assertNull($response->json('data.1.thumb'));
        $this->assertFalse($response->json('data.1.video'));
        $this->assertFalse($response->json('data.1.enabled'));

        $this->actingAs($editor, 'cms')->getJson($this->api('places/hero/banners?trashed=1'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $c->id);

        $this->actingAs($editor, 'cms')->getJson($this->api('places/promo/banners'))->assertOk()->assertExactJson(['data' => []]);
        $this->actingAs($editor, 'cms')->getJson($this->api('places/nowhere/banners'))->assertNotFound();
        $this->actingAs($editor, 'cms')->postJson($this->api('places/nowhere/banners'), ['values' => []])->assertForbidden();
    }

    #[Test]
    public function a_drag_writes_the_order_inside_the_place_and_nothing_else(): void
    {
        $this->picture();
        $editor = $this->editor();
        $a = $this->banner();
        $b = $this->banner();
        $c = $this->banner();
        $other = $this->banner('promo');

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('places/hero/reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertSame([$c->id, $a->id, $b->id], array_column(banners('hero')->get(), 'id'));

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('places/hero/reorder'), ['ids' => [$a->id, $other->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('places/hero/reorder'), ['ids' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('places/nowhere/reorder'), ['ids' => []])
            ->assertNotFound();

        $this->assertSame([$c->id, $a->id, $b->id], array_column(banners('hero')->get(), 'id'), 'a refused drag moves nothing');
    }

    #[Test]
    public function a_viewer_reads_and_writes_nothing(): void
    {
        $this->picture();
        $banner = $this->banner();
        $viewer = $this->editor(['banners.view']);

        $this->getJson($this->api('places'))->assertUnauthorized();

        $this->actingAs($viewer, 'cms')->getJson($this->api('places'))->assertOk();
        $this->actingAs($viewer, 'cms')->getJson($this->api($banner->id))->assertOk();
        $this->actingAs($viewer, 'cms')->postJson($this->api('places'), ['key' => 'x', 'title' => 'X'])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('places/hero/banners'), ['values' => []])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('places/hero/reorder'), ['ids' => [$banner->id]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->putJson($this->api($banner->id), ['values' => []])->assertForbidden();
        $this->actingAs($viewer, 'cms')->deleteJson($this->api($banner->id))->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api($banner->id.'/restore'))->assertForbidden();

        $this->actingAs($this->editor([]), 'cms')->getJson($this->api('places'))->assertForbidden();
    }
}
