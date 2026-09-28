<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Banners\Models\Place;
use WebxUi\Banners\Rendering\BannerQuery;
use WebxUi\Localization\Locales;

/**
 * `banners()` — what a template may show of a place, as cards (§5.2, §5.3) — and who a reader may
 * see at all (decision 12).
 */
final class HelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->picture();
    }

    #[Test]
    public function a_card_carries_what_a_template_prints(): void
    {
        $this->picture('media/ab/cd/tall.jpg');
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');
        $page = $this->page('booking');

        $banner = $this->banner(attributes: [
            'text' => ['en' => "Two lines\nof text"],
            'image' => ['path' => 'media/ab/cd/wide.jpg', 'alt' => ['en' => 'Flowers', 'ru' => 'Цветы']],
            'image_mobile' => ['path' => 'media/ab/cd/tall.jpg'],
            'video' => ['path' => 'media/ab/cd/clip.mp4'],
            'buttons' => [
                ['label' => ['en' => 'Book now'], 'link' => ['target' => 'entity', 'entity_type' => 'page', 'entity_id' => $page->id], 'variant' => 'primary'],
                ['label' => ['en' => 'Prices'], 'link' => ['target' => 'url', 'url' => 'https://example.org/prices', 'new_tab' => true, 'rel' => ['nofollow']], 'variant' => 'link'],
            ],
        ]);
        $banner->mergeExtra(['badge' => 'New'])->save();

        $card = banners('hero')->first();

        $this->assertIsArray($card);
        $this->assertSame(
            ['id', 'anchor', 'place', 'title', 'text', 'image', 'image_mobile', 'video', 'buttons', 'fields'],
            array_keys($card),
        );
        $this->assertSame($banner->id, $card['id']);
        $this->assertSame('banner-'.$banner->id, $card['anchor']);
        $this->assertSame('hero', $card['place']);
        $this->assertSame('Spring sale', $card['title']);
        $this->assertSame("Two lines\nof text", $card['text']);
        $this->assertIsString($card['image']['url']);
        $this->assertSame(1920, $card['image']['width']);
        $this->assertSame('Flowers', $card['image']['alt']);
        $this->assertStringContainsString('tall.jpg', (string) $card['image_mobile']['url']);
        $this->assertSame(['url', 'mime'], array_keys((array) $card['video']));
        $this->assertSame('video/mp4', $card['video']['mime']);
        $this->assertSame([
            ['label' => 'Book now', 'url' => $page->url('en'), 'new_tab' => false, 'rel' => null, 'variant' => 'primary'],
            ['label' => 'Prices', 'url' => 'https://example.org/prices', 'new_tab' => true, 'rel' => 'nofollow noopener noreferrer', 'variant' => 'link'],
        ], $card['buttons']);
        $this->assertSame(['badge' => 'New'], $card['fields']);
    }

    #[Test]
    public function a_banner_without_words_is_seen_in_every_language_and_one_with_words_only_in_theirs(): void
    {
        $bare = $this->banner(title: null);
        $english = $this->banner(title: 'Spring sale');
        $russianText = $this->banner(title: null, attributes: ['text' => ['ru' => 'Весна']]);

        $this->assertSame([$bare->id, $english->id], $this->ids(banners('hero')->locale('en')));
        $this->assertSame([$bare->id, $russianText->id], $this->ids(banners('hero')->locale('ru')));

        $this->assertSame('', banners('hero')->locale('ru')->only([$russianText->id])->first()['title'] ?? null, 'no fallback language');
    }

    #[Test]
    public function a_button_without_a_label_in_the_language_drops_out_and_the_banner_stays(): void
    {
        $this->banner(title: null, attributes: ['buttons' => [
            ['label' => ['en' => 'Go'], 'link' => self::url('/go'), 'variant' => 'primary'],
            ['label' => ['en' => 'Also', 'ru' => 'Ещё'], 'link' => self::url('/also'), 'variant' => 'primary'],
        ]]);

        $card = banners('hero')->locale('ru')->first();

        $this->assertIsArray($card);
        $this->assertSame(['Ещё'], array_column($card['buttons'], 'label'));
        $this->assertStringEndsWith('/ru/also', (string) $card['buttons'][0]['url'], 'a hand-written path takes the prefix of the language');
    }

    #[Test]
    public function turned_off_binned_and_pictureless_banners_are_not_seen(): void
    {
        $shown = $this->banner();
        $this->banner(attributes: ['enabled' => false]);
        $this->banner()->delete();
        $this->banner(attributes: ['image' => ['path' => 'media/gone.jpg']]);
        $this->banner(attributes: ['image' => null]);

        $this->assertSame([$shown->id], $this->ids(banners('hero')));
    }

    #[Test]
    public function the_limit_counts_what_is_shown(): void
    {
        $this->banner(attributes: ['image' => ['path' => 'media/gone.jpg']]);
        $this->banner(title: 'English only');
        $a = $this->banner(title: null);
        $b = $this->banner(title: null);
        $this->banner(title: null);

        $this->assertSame([$a->id, $b->id], $this->ids(banners('hero')->locale('ru')->take(2)));
        $this->assertCount(4, banners('hero')->take(0)->get(), 'zero is all');
        $this->assertCount(4, banners('hero')->take(null)->get());
    }

    #[Test]
    public function in_takes_a_key_an_id_a_place_or_a_list_in_the_order_named(): void
    {
        $hero1 = $this->banner('hero');
        $promo = $this->banner('promo');
        $hero2 = $this->banner('hero');
        $heroPlace = Place::query()->where('key', 'hero')->firstOrFail();

        $this->assertSame([$hero1->id, $hero2->id], $this->ids(banners('hero')));
        $this->assertSame([$hero1->id, $hero2->id], $this->ids(banners($heroPlace->id)));
        $this->assertSame([$hero1->id, $hero2->id], $this->ids(banners((string) $heroPlace->id)));
        $this->assertSame([$hero1->id, $hero2->id], $this->ids(banners($heroPlace)));
        $this->assertSame([$promo->id, $hero1->id, $hero2->id], $this->ids(banners(['promo', 'hero'])));
        $this->assertSame([$hero1->id, $hero2->id, $promo->id], $this->ids(banners(['hero', $promo->place_id])));
        $this->assertSame([$hero1->id, $promo->id, $hero2->id], $this->ids(banners()), 'no place — every banner, by position');
        $this->assertSame([$hero1->id, $promo->id, $hero2->id], $this->ids(banners([])));
    }

    #[Test]
    public function an_unknown_place_and_a_declared_one_without_a_row_are_empty(): void
    {
        $this->banner('hero');

        $this->assertSame([], banners('nowhere')->get());
        $this->assertSame([], banners(['nowhere', 'promo'])->get());
        $this->assertSame([], banners(999)->get());
        $this->assertNull(banners('promo')->first());
        $this->assertTrue(banners('promo')->isEmpty());
        $this->assertSame(0, Place::query()->where('key', 'promo')->count(), 'asking does not make a row');
    }

    #[Test]
    public function inside_a_place_the_order_is_its_position(): void
    {
        $a = $this->banner();
        $b = $this->banner();
        $c = $this->banner();
        $c->forceFill(['position' => 0])->save();

        $this->assertSame([$c->id, $a->id, $b->id], $this->ids(banners('hero')));
    }

    #[Test]
    public function only_sets_its_own_order_and_except_leaves_out(): void
    {
        $a = $this->banner();
        $b = $this->banner();
        $c = $this->banner();

        $this->assertSame([$c->id, $a->id], $this->ids(banners('hero')->only([$c->id, $a->id])));
        $this->assertSame([$a->id, $c->id], $this->ids(banners('hero')->except($b)));
        $this->assertSame([], banners('promo')->only([$a->id])->get());
    }

    #[Test]
    public function a_button_to_a_page_that_is_not_on_the_site_drops_out(): void
    {
        $draft = $this->page('draft', published: false);
        $binned = $this->page('binned');
        $binned->delete();

        $this->banner(attributes: ['buttons' => [
            ['label' => ['en' => 'Draft'], 'link' => ['target' => 'entity', 'entity_type' => 'page', 'entity_id' => $draft->id], 'variant' => 'primary'],
            ['label' => ['en' => 'Binned'], 'link' => ['target' => 'entity', 'entity_type' => 'page', 'entity_id' => $binned->id], 'variant' => 'primary'],
            ['label' => ['en' => 'Gone'], 'link' => ['target' => 'entity', 'entity_type' => 'page', 'entity_id' => 99999], 'variant' => 'primary'],
            ['label' => ['en' => 'Kept'], 'link' => self::url('/kept'), 'variant' => 'primary'],
        ]]);

        $this->assertSame(['Kept'], array_column((array) banners('hero')->first()['buttons'], 'label'));
    }

    #[Test]
    public function a_variant_taken_out_of_the_config_becomes_the_first_one(): void
    {
        $this->banner(attributes: ['buttons' => [
            ['label' => ['en' => 'Old'], 'link' => self::url('/old'), 'variant' => 'ghost'],
            ['label' => ['en' => 'None'], 'link' => self::url('/none')],
            ['label' => ['en' => 'Second'], 'link' => self::url('/second'), 'variant' => 'secondary'],
        ]]);

        $this->assertSame(['primary', 'primary', 'secondary'], array_column((array) banners('hero')->first()['buttons'], 'variant'));

        config()->set('webx-banners.variants', ['cta' => 'Call to action']);

        $this->assertSame(['cta', 'cta', 'cta'], array_column((array) banners('hero')->first()['buttons'], 'variant'));
    }

    #[Test]
    public function the_query_can_be_iterated_and_counted_and_is_a_record_query(): void
    {
        $this->banner();
        $this->banner();

        $query = banners('hero');

        $this->assertInstanceOf(BannerQuery::class, $query);
        $this->assertCount(2, $query);
        $this->assertCount(2, iterator_to_array($query));
    }

    #[Test]
    public function the_language_is_the_one_the_page_is_drawn_in_unless_asked(): void
    {
        $this->banner(title: 'English');

        $this->app->make(Locales::class)->use('ru');

        $this->assertSame([], banners('hero')->get());
        $this->assertCount(1, banners('hero')->locale('en')->get());
    }

    #[Test]
    public function a_template_prints_the_place_and_an_empty_or_unknown_place_prints_nothing(): void
    {
        $this->banner(attributes: ['buttons' => [['label' => ['en' => 'Book'], 'link' => self::url('/book'), 'variant' => 'primary']]]);

        $html = view()->file(__DIR__.'/Fixtures/views/hero.blade.php', ['place' => 'hero'])->render();

        $this->assertStringContainsString('class="hero hero--slider"', $html);
        $this->assertStringContainsString('<h2>Spring sale</h2>', $html);
        $this->assertStringContainsString('/book">Book</a>', $html);

        foreach (['promo', 'nowhere'] as $place) {
            $empty = view()->file(__DIR__.'/Fixtures/views/hero.blade.php', ['place' => $place])->render();

            $this->assertStringNotContainsString('<section', $empty);
            $this->assertStringContainsString('<main>', $empty);
        }
    }

    /**
     * @return list<int>
     */
    private function ids(BannerQuery $query): array
    {
        return array_column($query->get(), 'id');
    }
}
