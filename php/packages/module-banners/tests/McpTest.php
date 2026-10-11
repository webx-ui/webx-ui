<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Banners by their other doors (§5.7): the same places, the same screen, the same order code.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_section_offers_its_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            [
                'banners_places', 'banners_place_create', 'banners_place_delete', 'banners_list', 'banners_get',
                'banners_create', 'banners_update', 'banners_delete', 'banners_reorder',
            ],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('banners')),
        );

        $this->assertSame(['banners.view', 'banners.manage'], $registry->tool('banners_list')->permissions());
        $this->assertSame(['banners.manage'], $registry->tool('banners_reorder')->permissions());
        $this->assertContains('banners://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['banners.view']);

        $this->agent('banners_places', [], $reader)->assertOk();
        $this->agent('banners_create', ['place' => 'hero', 'image' => 'x'], $reader)->assertHasErrors(['[banners.manage]']);
    }

    #[Test]
    public function the_places_are_the_declared_ones_and_somebodys_own(): void
    {
        $places = $this->content($this->agent('banners_places'));

        $this->assertSame(['single', 'random', 'slider'], $places['layouts']);
        $this->assertSame(['primary', 'secondary', 'link'], $places['variants']);
        $this->assertSame(['hero', 'promo', 'notice'], array_column($places['places'], 'key'));

        $created = $this->content($this->agent('banners_place_create', ['key' => 'sidebar', 'title' => 'Sidebar']));
        $this->assertSame('sidebar', $created['place']['key']);
        $this->assertFalse($created['place']['declared']);

        $this->agent('banners_place_create', ['key' => 'hero', 'title' => 'Again'])->assertHasErrors(['key:']);
        $this->agent('banners_place_create', ['key' => '9lives', 'title' => 'Bad'])->assertHasErrors(['key:']);

        $this->agent('banners_place_delete', ['place' => 'hero'])->assertHasErrors(['declared']);
        $this->agent('banners_place_delete', ['place' => 'nowhere'])->assertHasErrors(['no place [nowhere]', 'hero, promo, notice, sidebar']);

        // The bin counts: a banner somebody meant to bring back would go with the place.
        $this->picture();
        $this->banner('sidebar')->delete();
        $this->agent('banners_place_delete', ['place' => 'sidebar'])->assertHasErrors(['holds 1 banner,']);

        Banner::withTrashed()->forceDelete();
        $this->agent('banners_place_delete', ['place' => 'sidebar'])->assertOk();
        $this->assertFalse(Place::query()->where('key', 'sidebar')->exists());
    }

    #[Test]
    public function create_writes_through_the_screen(): void
    {
        $this->picture('media/ab/cd/wide.jpg');
        $this->picture('media/ab/cd/tall.jpg');
        $about = $this->page('about');

        $created = $this->content($this->agent('banners_create', [
            'place' => 'promo',
            'image' => 'media/ab/cd/wide.jpg',
            'image_mobile' => ['path' => 'media/ab/cd/tall.jpg', 'alt' => ['en' => 'Tall']],
            'title' => 'Spring sale',
            'text' => ['en' => 'Two lines.', 'ru' => 'Две строки.'],
            'buttons' => [
                ['label' => 'Read more', 'link' => ['entity_type' => 'page', 'entity_id' => $about->id], 'variant' => 'primary'],
                ['label' => ['en' => 'Call', 'ru' => 'Звонок'], 'link' => '/contact'],
            ],
        ]));

        // Off until somebody asks (decision 13); a plain string is the default language.
        $this->assertFalse($created['banner']['enabled']);
        $this->assertSame('promo', $created['banner']['place']);
        $this->assertSame(['en' => 'Spring sale'], $created['values']['title']);
        $this->assertSame(['en', 'ru'], $created['banner']['written_in']);
        $this->assertSame('media/ab/cd/wide.jpg', $created['banner']['image']);
        $this->assertSame('entity', $created['values']['buttons'][0]['link']['target']);
        $this->assertSame('/contact', $created['values']['buttons'][1]['link']['url']);
        $this->assertNull($created['values']['buttons'][1]['variant']);

        // The declared place got its row with its first banner.
        $this->assertTrue(Place::query()->where('key', 'promo')->exists());

        $this->agent('banners_update', ['banner' => $created['banner']['id'], 'values' => ['enabled' => true, 'title' => ['ru' => 'Весенняя распродажа']]])->assertOk();

        $banner = Banner::query()->findOrFail($created['banner']['id']);
        $this->assertTrue($banner->enabled);
        $this->assertSame(['en' => 'Spring sale', 'ru' => 'Весенняя распродажа'], $banner->getTranslations('title'));
        $this->assertCount(2, $banner->buttonRows(), 'buttons left out keep what they had');
    }

    #[Test]
    public function a_refused_create_leaves_nothing_behind(): void
    {
        $this->picture('media/ab/cd/wide.jpg');
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');

        $this->agent('banners_create', ['place' => 'hero', 'image' => 'media/no/such.jpg'])->assertHasErrors(['no file [media/no/such.jpg]']);
        $this->agent('banners_create', ['place' => 'nowhere', 'image' => 'media/ab/cd/wide.jpg'])->assertHasErrors(['no place [nowhere]']);
        $this->agent('banners_create', ['place' => 'hero', 'image' => 'media/ab/cd/clip.mp4'])->assertHasErrors(['image:']);
        $this->agent('banners_create', ['place' => 'hero', 'image' => null, 'video' => 'media/ab/cd/clip.mp4'])->assertHasErrors(['image:']);
        $this->agent('banners_create', ['place' => 'hero', 'image' => 'media/ab/cd/wide.jpg', 'buttons' => [['label' => 'Go']]])
            ->assertHasErrors(['Button 1 needs a link']);
        $this->agent('banners_create', ['place' => 'hero', 'image' => 'media/ab/cd/wide.jpg', 'buttons' => [['label' => 'Go', 'link' => 'javascript:alert(1)']]])
            ->assertHasErrors(['buttons.0.link']);
        $this->agent('banners_create', ['place' => 'hero', 'image' => 'media/ab/cd/wide.jpg', 'buttons' => array_fill(0, 4, ['label' => 'Go', 'link' => '/go'])])
            ->assertHasErrors(['at most 3']);

        $this->assertSame(0, Banner::withTrashed()->count());
        $this->assertSame(0, Place::query()->count(), 'no lazy row of a declared place either');
    }

    #[Test]
    public function a_look_the_site_does_not_have_is_refused_with_the_ones_it_has(): void
    {
        $this->picture();

        $this->agent('banners_create', ['place' => 'hero', 'image' => 'media/ab/cd/wide.jpg', 'buttons' => [['label' => 'Go', 'link' => '/go', 'variant' => 'ghost']]])
            ->assertHasErrors(['no look [ghost]', 'primary, secondary, link']);

        $this->assertSame(0, Banner::withTrashed()->count());
    }

    #[Test]
    public function a_look_since_dropped_goes_back_as_it_came(): void
    {
        $this->picture();
        $banner = $this->banner('hero', attributes: ['buttons' => [['label' => ['en' => 'Go'], 'link' => self::url('/go'), 'variant' => 'ghost']]]);

        $buttons = $this->content($this->agent('banners_get', ['banner' => $banner->id]))['values']['buttons'];
        $buttons[] = ['label' => 'More', 'link' => '/more', 'variant' => 'link'];

        $this->agent('banners_update', ['banner' => $banner->id, 'values' => ['buttons' => $buttons]])->assertOk();

        $this->assertSame(['ghost', 'link'], array_column($banner->refresh()->buttonRows(), 'variant'));
    }

    #[Test]
    public function moving_puts_it_last_in_the_new_place(): void
    {
        $this->picture();
        $first = $this->banner('promo', 'Promo one');
        $moved = $this->banner('hero', 'Moved');

        $this->agent('banners_update', ['banner' => $moved->id, 'place' => 'promo'])->assertOk();

        $listed = $this->content($this->agent('banners_list', ['place' => 'promo']));
        $this->assertSame([$first->id, $moved->id], array_column($listed['banners'], 'id'));
        $this->assertSame(['promo', 'promo'], array_column($listed['banners'], 'place'));
    }

    #[Test]
    public function reordering_is_the_one_order_and_the_list_shows_it(): void
    {
        $this->picture();
        $first = $this->banner('hero', 'First');
        $second = $this->banner('hero', 'Second');
        $third = $this->banner('hero', 'Third');
        $elsewhere = $this->banner('promo', 'Elsewhere');

        $listed = $this->content($this->agent('banners_reorder', ['place' => 'hero', 'banners' => [$third->id, $first->id]]));

        $this->assertSame([$third->id, $first->id, $second->id], array_column($listed['banners'], 'id'));
        $this->agent('banners_reorder', ['place' => 'hero', 'banners' => [$elsewhere->id]])->assertHasErrors(['not in the place [hero]']);
        $this->agent('banners_reorder', ['place' => 'hero', 'banners' => ['Spring']])->assertHasErrors(['its id']);
    }

    #[Test]
    public function delete_puts_it_in_the_bin(): void
    {
        $this->picture();
        $banner = $this->banner();

        $this->agent('banners_delete', ['banner' => $banner->id, 'dry_run' => true])->assertOk();
        $this->assertFalse($banner->refresh()->trashed());

        $this->agent('banners_delete', ['banner' => (string) $banner->id])->assertOk();

        $this->assertTrue($banner->refresh()->trashed());
        $this->assertSame([$banner->id], array_column($this->content($this->agent('banners_list', ['trashed' => true]))['banners'], 'id'));
        $this->agent('banners_update', ['banner' => $banner->id, 'values' => ['enabled' => false]])->assertHasErrors(['in the bin']);
    }

    #[Test]
    public function the_catalogue_lists_every_place_with_its_banners_in_order(): void
    {
        $this->picture();
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');
        $spring = $this->banner('hero', 'Spring', ['title' => ['en' => 'Spring', 'ru' => 'Весна'], 'video' => ['path' => 'media/ab/cd/clip.mp4']]);
        $off = $this->banner('hero', 'Off', ['enabled' => false]);
        $this->banner('hero', 'Gone')->delete();

        $catalog = ($this->resource('banners://catalog')->handler)();

        $this->assertSame('Primary', $catalog['variants']['primary']);
        $this->assertSame(['hero', 'promo', 'notice'], array_column($catalog['places'], 'key'));
        $this->assertSame([$spring->id, $off->id], array_column($catalog['places'][0]['banners'], 'id'));
        $this->assertSame(['en', 'ru'], $catalog['places'][0]['banners'][0]['written_in']);
        $this->assertTrue($catalog['places'][0]['banners'][0]['has_video']);
        $this->assertFalse($catalog['places'][0]['banners'][1]['enabled']);
        $this->assertSame([], $catalog['places'][1]['banners']);
        $this->assertStringContainsString("banners('<key>')", $catalog['usage']);
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
