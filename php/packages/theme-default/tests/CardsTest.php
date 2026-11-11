<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\Widgets;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The `cards` block (THEMES §9.1): the grid it always was, a carousel over `<x-webx-slider>`, the
 * labels of a card and its photos over `<x-webx-photos>`. A block saved before any of them prints
 * what it printed before — `Fixtures/before/cards.blade.php` is the template as it was.
 */
final class CardsTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

    private const array HALL = ['url' => '/media/hall.jpg', 'alt' => 'The hall', 'width' => 1200, 'height' => 800];

    private const array KITCHEN = ['url' => '/media/kitchen.jpg', 'alt' => 'The kitchen', 'width' => 1200, 'height' => 800];

    private const array VIEW = ['url' => '/media/view.jpg', 'alt' => 'The view', 'width' => 1200, 'height' => 800];

    private const array LINK = ['target' => 'url', 'entity_type' => null, 'entity_id' => null, 'url' => '/flats/12', 'hash' => null, 'new_tab' => false, 'rel' => []];

    /** The fields a block saved before this stage did not have. */
    private const array NEW = ['view', 'arrows', 'dots', 'endless', 'autoplay', 'interval'];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('webx-themes.theme', self::NAME);
        $app->resolving(ThemeLocator::class, function (ThemeLocator $locator): void {
            $locator->register(self::NAME, self::path());
        });
    }

    #[Test]
    public function a_block_saved_before_prints_what_it_printed_before(): void
    {
        $saved = [
            'sample' => [],
            'photos, linked, four across' => ['columns' => '4', 'cards' => [
                ['image' => self::HALL, 'title' => 'Hall', 'text' => 'Wide', 'link_label' => 'More', 'link' => self::LINK, 'link_url' => ''],
                ['image' => null, 'title' => 'Words only', 'text' => '', 'link_label' => '', 'link' => null, 'link_url' => '/old'],
                ['image' => ['url' => '', 'alt' => ''], 'title' => '', 'text' => '', 'link_label' => '', 'link' => null, 'link_url' => ''],
            ]],
            'icons, two across' => ['columns' => '2', 'pictures' => 'icon', 'no_reveal' => true, 'cards' => [
                ['image' => self::HALL, 'title' => 'One', 'text' => 'Text'],
                ['image' => self::KITCHEN, 'title' => 'Two'],
            ]],
            'a heading alone' => ['cards' => []],
        ];

        foreach ($saved as $case => $values) {
            $before = Blade::render((string) file_get_contents(__DIR__.'/Fixtures/before/cards.blade.php'), $this->data($values, saved: true), deleteCachedView: true);

            $this->assertSame(self::tight($before), self::tight($this->render($values, saved: true)), $case);
            // The new fields at their defaults are the same block.
            $this->assertSame(self::tight($before), self::tight($this->render($values)), "{$case}, defaults");
        }

        $this->assertSame([], app(Widgets::class)->claimed(), 'A grid of one photo a card claims no script.');
    }

    #[Test]
    public function the_new_fields_default_to_the_grid_and_show_only_with_the_carousel(): void
    {
        $this->assertSame('grid', self::field('view')['default']);
        $this->assertSame(['grid', 'slider'], array_column(self::field('view')['props']['options'], 'value'));

        foreach (['arrows' => true, 'dots' => true, 'endless' => true, 'autoplay' => false, 'interval' => 5] as $id => $default) {
            $this->assertSame($default, self::field($id)['default'], $id);
            $this->assertStringContainsString('"when":"view","is":"slider"', (string) json_encode(self::field($id)['visible']), $id);
        }

        $this->assertSame(['min' => 2, 'max' => 30], self::field('interval')['props']);
        $this->assertLessThanOrEqual(255, mb_strlen((string) self::type()['description']));

        $card = array_column(self::field('cards')['children'], null, 'id');
        $this->assertSame('wx-gallery', $card['photos']['type']);
        $this->assertSame('wx-repeater', $card['chips']['type']);
        $this->assertSame([['id' => 'label', 'type' => 'wx-input', 'label' => 'Label', 'localized' => true, 'props' => ['maxlength' => 40]]], $card['chips']['children']);
        $this->assertSame(['image', 'photos', 'title', 'chips', 'text', 'link_label', 'link', 'link_url'], array_keys($card));
    }

    #[Test]
    public function the_carousel_is_a_slider_of_the_same_cards_as_many_across_as_the_grid(): void
    {
        $html = $this->render(['view' => 'slider', 'columns' => '3', 'heading' => 'Homes ==by the river==', 'cards' => [
            ['image' => self::HALL, 'title' => 'One', 'link' => self::LINK, 'link_label' => 'More'],
            ['title' => 'Two'],
            ['title' => 'Three'],
            ['title' => ''],
        ]]);

        $this->assertSame(['slider'], app(Widgets::class)->claimed());
        $this->assertStringContainsString('class="b-cards b-cards--3 b-cards--photo b-cards--carousel"', $html);
        $this->assertStringNotContainsString('b-cards__grid', $html);
        $this->assertMatchesRegularExpression('~<div class="b-cards__carousel"[^>]*>\s*<section id="webx-slider-[\w-]+" data-webx-slider=~', $html);
        $this->assertStringContainsString('class="webx-slider webx-slider--cards"', $html);
        $this->assertStringContainsString('aria-label="Homes by the river"', $html);
        // The widths of the grid: one, two from 32rem, three from 52rem, not four.
        $this->assertMatchesRegularExpression('~--webx-slider-per-view: 1; \} @container webx-slider \(min-width: 512px\) \{ #[\w-]+ \{ --webx-slider-per-view: 2; \} \} @container webx-slider \(min-width: 832px\) \{ #[\w-]+ \{ --webx-slider-per-view: 3; \} \}</style>~', $html);
        $this->assertStringContainsString('&quot;loop&quot;:true,&quot;autoplay&quot;:0', $html);
        $this->assertStringContainsString('webx-slider__next', $html);
        $this->assertStringContainsString('webx-slider__pagination', $html);
        $this->assertSame(3, substr_count($html, 'class="webx-slider__slide"'));
        $this->assertMatchesRegularExpression('~class="webx-slider__slide">\s*<div class="b-cards__card is-linked"\s*>\s*<img class="b-cards__picture" src="/media/hall.jpg"~', $html);
    }

    #[Test]
    public function the_carousel_s_switches_are_the_slider_s(): void
    {
        $html = $this->render(['view' => 'slider', 'columns' => '2', 'arrows' => false, 'dots' => false, 'endless' => false, 'autoplay' => true, 'interval' => 99, 'cards' => [['title' => 'One'], ['title' => 'Two'], ['title' => 'Three']]]);

        $this->assertStringContainsString('&quot;loop&quot;:false,&quot;autoplay&quot;:30000', $html);
        $this->assertStringNotContainsString('webx-slider__next', $html);
        $this->assertStringNotContainsString('webx-slider__pagination', $html);
        $this->assertStringContainsString('webx-slider__pause', $html);
        $this->assertStringNotContainsString('min-width: 832px', $html);
    }

    #[Test]
    public function labels_stand_under_the_title_the_empty_ones_left_out(): void
    {
        $html = $this->render(['cards' => [['title' => 'Flat 12', 'text' => 'Bright', 'chips' => [['label' => '2 rooms'], ['label' => '  '], ['label' => '54 m²'], 'junk']]]]);

        $this->assertMatchesRegularExpression('~</h3>\s*<ul class="b-cards__chips" role="list">\s*<li class="b-cards__chip">2 rooms</li>\s*<li class="b-cards__chip">54 m²</li>\s*</ul>\s*<p class="b-cards__text">Bright</p>~', $html);
        $this->assertStringNotContainsString('b-cards__chips', $this->render(['cards' => [['title' => 'Flat', 'chips' => [['label' => '']]]]]));

        $styles = (string) self::type()['styles'];
        $this->assertStringContainsString('.b-cards__chip {', $styles);
        $this->assertStringContainsString('background: var(--site-color-surface-muted)', $styles);
    }

    #[Test]
    public function several_photos_turn_in_a_photo_card_and_one_stays_a_picture(): void
    {
        $html = $this->render(['cards' => [
            ['image' => self::HALL, 'photos' => [self::KITCHEN, ['url' => null], self::VIEW], 'title' => 'Flat 12', 'link' => self::LINK],
            ['image' => null, 'photos' => [self::KITCHEN], 'title' => 'One photo'],
            ['image' => null, 'photos' => [self::HALL, self::VIEW], 'title' => ''],
        ]]);

        $this->assertSame(['photos'], app(Widgets::class)->claimed());
        // A linked card is the surface of the swipe: its link lies over the frame.
        $this->assertMatchesRegularExpression('~<li class="b-cards__card is-linked"\s+data-webx-photos-surface\s*>\s*<div style="--webx-photos-ratio: 3 / 2; --webx-photos-interval: 5000ms" role="group" aria-roledescription="carousel" aria-label="Flat 12"~', $html);
        $this->assertSame(3 + 2, substr_count($html, 'class="webx-photos__photo'));
        $this->assertSame(3 + 2, substr_count($html, 'class="webx-photos__bar"'));
        // One photo, picked in the more photos only: the picture it always was.
        $this->assertMatchesRegularExpression('~<li class="b-cards__card"\s*>\s*<img class="b-cards__picture" src="/media/kitchen.jpg"~', $html);
        // Photos without words are a card too, its photos named by the widget's word.
        $this->assertStringContainsString('aria-label="Photos"', $html);
        $this->assertSame(2, substr_count($html, 'data-webx-photos='));
    }

    #[Test]
    public function an_icon_card_shows_its_first_picture_only(): void
    {
        $html = $this->render(['pictures' => 'icon', 'cards' => [['image' => self::HALL, 'photos' => [self::KITCHEN], 'title' => 'Icon']]]);

        $this->assertStringNotContainsString('webx-photos', $html);
        $this->assertStringContainsString('<img class="b-cards__picture" src="/media/hall.jpg"', $html);
        $this->assertSame([], app(Widgets::class)->claimed());
    }

    #[Test]
    public function photos_in_a_carousel_turn_inside_their_slide(): void
    {
        $html = $this->render(['view' => 'slider', 'cards' => [
            ['photos' => [self::HALL, self::KITCHEN], 'title' => 'One', 'chips' => [['label' => 'New']]],
            ['title' => 'Two'],
        ]]);

        $claimed = app(Widgets::class)->claimed();
        sort($claimed);
        $this->assertSame(['photos', 'slider'], $claimed);
        $this->assertMatchesRegularExpression('~<div class="b-cards__card"\s*>\s*<div style="--webx-photos-ratio~', $html);
        $this->assertStringContainsString('<li class="b-cards__chip">New</li>', $html);
    }

    /**
     * The template on the sample, every field a variable as the renderer makes it.
     *
     * @param  array<string, mixed>  $values
     */
    private function render(array $values = [], bool $saved = false): string
    {
        return Blade::render((string) self::type()['template'], $this->data($values, $saved), deleteCachedView: true);
    }

    /**
     * Saved: the fields of this stage are absent, as a block saved before them reads.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function data(array $values, bool $saved): array
    {
        $type = self::type();
        $data = [...array_fill_keys(array_column($type['schema'], 'id'), null), ...$type['sample'], ...$values];

        if ($saved) {
            foreach (self::NEW as $id) {
                unset($data[$id]);
            }

            $data['cards'] = array_map(static function (mixed $card): mixed {
                if (is_array($card)) {
                    unset($card['photos'], $card['chips']);
                }

                return $card;
            }, is_array($data['cards']) ? $data['cards'] : []);
        }

        return $data;
    }

    /** The indent a template line left out leaves before the next one, which the browser drops. */
    private static function tight(string $html): string
    {
        return trim((string) preg_replace(['~\s+~', '~ >~'], [' ', '>'], $html));
    }

    /** @return array<string, mixed> */
    private static function field(string $id): array
    {
        foreach (self::type()['schema'] as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private static function type(): array
    {
        return json_decode((string) file_get_contents(self::path().'/blocks/cards.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }
}
