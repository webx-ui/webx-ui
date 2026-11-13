<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\View\Sliders;
use WebxUi\Widgets\Widgets;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The `cards` block (THEMES §9.1): the grid it always was, a carousel over `<x-webx-slider>`, the
 * labels of a card and its photos over `<x-webx-photos>`, the photo under the words (`overlay`), the
 * section's button and the words beside a carousel (`split`). A block saved before any of them
 * prints what it printed before — `Fixtures/before/cards.blade.php` is the template before the
 * carousel, `Fixtures/before/cards-carousel.blade.php` the one before the overlay and the split.
 */
final class CardsTest extends TestCase
{
    private const string NAME = 'webx-ui/theme-default';

    private const array HALL = ['url' => '/media/hall.jpg', 'alt' => 'The hall', 'width' => 1200, 'height' => 800];

    private const array KITCHEN = ['url' => '/media/kitchen.jpg', 'alt' => 'The kitchen', 'width' => 1200, 'height' => 800];

    private const array VIEW = ['url' => '/media/view.jpg', 'alt' => 'The view', 'width' => 1200, 'height' => 800];

    private const array LINK = ['target' => 'url', 'entity_type' => null, 'entity_id' => null, 'url' => '/flats/12', 'hash' => null, 'new_tab' => false, 'rel' => []];

    /** The fields a block saved before this stage did not have. */
    private const array NEW = ['eyebrow', 'view', 'arrows', 'dots', 'endless', 'autoplay', 'interval', 'button_label', 'button_link'];

    /** The fields of the overlay and the split stage. */
    private const array SECTION_BUTTON = ['button_label', 'button_link'];

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
    public function the_line_above_the_heading_stands_first_in_the_head_and_empty_prints_nothing(): void
    {
        $this->assertSame(['id' => 'eyebrow', 'type' => 'wx-input', 'label' => 'Above the heading', 'localized' => true, 'props' => ['maxlength' => 80]], self::field('eyebrow'));
        $this->assertSame('eyebrow', self::type()['schema'][0]['id'], 'above the heading in the form too');
        $this->assertSame('', self::type()['sample']['eyebrow']);

        $html = $this->render(['eyebrow' => 'Residences & resort', 'heading' => 'Homes ==by the river==']);
        $this->assertMatchesRegularExpression('~<div class="b-cards__head"[^>]*>\s*<p class="b-cards__eyebrow">Residences &amp; resort</p>\s*<h2 class="b-cards__heading">~', $html);
        $this->assertStringContainsString(".b-cards__eyebrow {\n", (string) self::type()['styles']);

        // On its own, with no heading and no cards, it is still a head of its own.
        $alone = $this->render(['eyebrow' => 'Soon', 'heading' => '', 'intro' => '', 'cards' => []]);
        $this->assertStringContainsString('<p class="b-cards__eyebrow">Soon</p>', $alone);

        foreach (['', null] as $empty) {
            $this->assertStringNotContainsString('b-cards__eyebrow', $this->render(['eyebrow' => $empty]));
        }
        $this->assertStringNotContainsString('b-cards', $this->render(['eyebrow' => '', 'heading' => '', 'cards' => []]));
    }

    #[Test]
    public function the_new_fields_default_to_the_grid_and_show_only_with_the_carousel(): void
    {
        $this->assertSame('grid', self::field('view')['default']);
        $this->assertSame(['grid', 'slider', 'split'], array_column(self::field('view')['props']['options'], 'value'));

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

    #[Test]
    public function a_block_saved_before_the_overlay_and_the_split_prints_what_it_printed_before(): void
    {
        $saved = [
            'sample' => [],
            'a carousel of photos and labels' => ['view' => 'slider', 'eyebrow' => 'Homes', 'autoplay' => true, 'interval' => 7, 'cards' => [
                ['image' => self::HALL, 'photos' => [self::KITCHEN, self::VIEW], 'title' => 'Flat 12', 'chips' => [['label' => '2 rooms']], 'text' => 'Bright', 'link_label' => 'More', 'link' => self::LINK],
                ['image' => self::KITCHEN, 'title' => 'Flat 14', 'link_url' => '/old'],
            ]],
            'icons, four across' => ['columns' => '4', 'pictures' => 'icon', 'cards' => [['image' => self::HALL, 'title' => 'One', 'text' => 'Text']]],
            'a grid of photos turning' => ['cards' => [['photos' => [self::HALL, self::VIEW], 'title' => 'Two photos', 'link' => self::LINK]]],
            'an eyebrow alone' => ['eyebrow' => 'Soon', 'heading' => '', 'intro' => '', 'cards' => []],
        ];

        foreach ($saved as $case => $values) {
            $data = $this->data($values, saved: false);

            foreach (self::SECTION_BUTTON as $id) {
                unset($data[$id]);
            }

            $before = Blade::render((string) file_get_contents(__DIR__.'/Fixtures/before/cards-carousel.blade.php'), $data, deleteCachedView: true);
            // Each render a page of its own: a second slider on one page would take another id.
            $this->app->forgetInstance(Sliders::class);
            $this->assertSame(self::tight($before), self::tight(Blade::render((string) self::type()['template'], $data, deleteCachedView: true)), $case);
            $this->app->forgetInstance(Sliders::class);
            $this->assertSame(self::tight($before), self::tight($this->render($values)), "{$case}, defaults");
            $this->app->forgetInstance(Sliders::class);
        }
    }

    #[Test]
    public function the_section_s_button_stands_under_the_words_of_the_head(): void
    {
        $this->assertSame(['eyebrow', 'heading', 'intro', 'button_label', 'button_link', 'columns'], array_slice(array_column(self::type()['schema'], 'id'), 0, 6));
        $this->assertSame(['id' => 'button_label', 'type' => 'wx-input', 'label' => 'Button', 'localized' => true, 'help' => 'Under the words above the cards.'], self::field('button_label'));
        $this->assertSame(['files' => true, 'allowNone' => false], self::field('button_link')['props']);
        $this->assertSame('', self::type()['sample']['button_label']);

        $html = $this->render(['intro' => 'Three of them.', 'button_label' => 'All flats', 'button_link' => self::LINK]);
        $this->assertMatchesRegularExpression('~<p class="b-cards__intro">Three of them\.</p>\s*<div class="b-cards__actions">\s*<a class="b-cards__button" href="/flats/12"[^>]*>All flats</a>\s*</div>\s*</div>~', $html);

        // Words without a link, or a link without words, are no button.
        $this->assertStringNotContainsString('b-cards__actions', $this->render(['button_label' => 'All flats']));
        $this->assertStringNotContainsString('b-cards__actions', $this->render(['button_label' => '', 'button_link' => self::LINK]));

        // A button alone is a head of its own.
        $alone = $this->render(['heading' => '', 'intro' => '', 'cards' => [], 'button_label' => 'Call us', 'button_link' => self::LINK]);
        $this->assertMatchesRegularExpression('~<div class="b-cards__head"[^>]*>\s*<div class="b-cards__actions">~', $alone);
    }

    #[Test]
    public function an_overlay_card_is_the_photo_with_its_words_on_glass_and_the_bars_over_the_link(): void
    {
        $this->assertSame(['photo', 'icon', 'overlay'], array_column(self::field('pictures')['props']['options'], 'value'));

        $html = $this->render(['pictures' => 'overlay', 'columns' => '2', 'cards' => [
            ['image' => self::HALL, 'photos' => [self::KITCHEN, self::VIEW], 'title' => 'Flat 12', 'text' => 'Bright', 'link_label' => 'See the flat', 'link' => self::LINK],
            ['image' => self::KITCHEN, 'title' => 'Flat 14', 'text' => 'Quiet'],
        ]]);

        $this->assertStringContainsString('class="b-cards b-cards--2 b-cards--overlay"', $html);
        // The same card as a photo card: the photos turning, then the words, the link stretched over it.
        $this->assertMatchesRegularExpression('~<li class="b-cards__card is-linked"\s+data-webx-photos-surface\s*>\s*<div style="--webx-photos-ratio: 3 / 2;[^"]*" role="group"~', $html);
        $this->assertMatchesRegularExpression('~<a class="b-cards__link" href="/flats/12"[^>]*>Flat 12</a>~', $html);
        $this->assertStringContainsString('<span class="b-cards__more" aria-hidden="true">See the flat', $html);
        $this->assertSame(['photos'], app(Widgets::class)->claimed());

        $styles = self::rules((string) self::type()['styles']);
        // The pane lifts itself and the link over the photo without becoming the link's box.
        $this->assertStringContainsString('z-index: 1;', $styles['.b-cards--overlay .b-cards__body']);
        $this->assertStringNotContainsString('position', $styles['.b-cards--overlay .b-cards__body']);
        $this->assertStringContainsString('backdrop-filter: blur(var(--cards-plate-blur))', $styles['.b-cards--overlay .b-cards__body']);
        // The bars and the pause button over the pane and its link: a click on them opens nothing.
        $this->assertStringContainsString('z-index: 2;', $styles['.b-cards--overlay .webx-photos__bars']);
        $this->assertStringContainsString('z-index: 2;', $styles['.b-cards--overlay .webx-photos__pause']);
        // The pane's spacing is the block's own variables, on the site's spacing.
        foreach (['--cards-plate-padding: var(--site-space-6)', '--cards-plate-gap: var(--site-space-5)', '--cards-plate-actions-space: var(--site-space-5)'] as $knob) {
            $this->assertStringContainsString($knob, $styles['.b-cards--overlay']);
        }
    }

    #[Test]
    public function the_split_is_the_words_beside_a_carousel_of_one_card_with_lines_for_pagination(): void
    {
        $this->assertSame(['when' => 'view', 'not' => 'split'], self::field('columns')['visible']);
        $this->assertSame(['when' => 'view', 'is' => 'slider'], self::field('arrows')['visible']);
        $this->assertSame(['when' => 'view', 'is' => 'slider'], self::field('dots')['visible']);
        $this->assertStringContainsString('{"when":"view","is":"split"}', (string) json_encode(self::field('interval')['visible']));

        // The photo across the card; under the words it is the pane over the carousel, below.
        $html = $this->render(['view' => 'split', 'pictures' => 'photo', 'columns' => '4', 'arrows' => true, 'dots' => false, 'autoplay' => true, 'interval' => 6, 'heading' => 'Homes', 'button_label' => 'All flats', 'button_link' => self::LINK, 'cards' => [
            ['image' => self::HALL, 'title' => 'One'],
            ['image' => self::KITCHEN, 'title' => 'Two'],
        ]]);

        $this->assertStringContainsString('class="b-cards b-cards--4 b-cards--photo b-cards--carousel b-cards--split"', $html);
        $this->assertStringNotContainsString('b-cards__plate', $html);
        $this->assertMatchesRegularExpression('~</div>\s*<div class="b-cards__carousel"[^>]*>\s*<section id="webx-slider-[\w-]+" data-webx-slider=~', $html);
        // One card at every width, no arrows, the pagination always, turning every six seconds.
        $this->assertMatchesRegularExpression('~--webx-slider-per-view: 1; \}</style>~', $html);
        $this->assertStringNotContainsString('min-width: 512px', $html);
        $this->assertStringNotContainsString('webx-slider__next', $html);
        $this->assertStringContainsString('webx-slider__pagination', $html);
        $this->assertStringContainsString('&quot;autoplay&quot;:6000', $html);
        $this->assertStringContainsString('webx-slider__pause', $html);

        $styles = self::rules((string) self::type()['styles']);
        $this->assertStringContainsString('flex-wrap: wrap', $styles['.b-cards--split']);
        $this->assertStringContainsString('height: 3px', $styles['.b-cards--split .webx-slider__bullet::before']);
        // On a narrow screen the carousel covers the gutters of the main column.
        $this->assertStringContainsString('margin-inline: calc(-1 * var(--site-section-gutter, var(--site-gutter)))', $styles['.b-cards--split:where(.site-main > *) > .b-cards__carousel, .b-cards--overlay:where(.site-main > *) > .b-cards__carousel, .b-cards--overlay:where(.site-main > *) > .b-cards__grid']);
    }

    #[Test]
    public function split_with_the_photo_under_the_words_is_one_pane_over_the_carousel_with_the_lines_in_it(): void
    {
        $html = $this->render(['view' => 'split', 'pictures' => 'overlay', 'autoplay' => true, 'interval' => 5, 'heading' => 'Places', 'cards' => [
            ['image' => self::HALL, 'title' => 'One', 'text' => 'Short.', 'link_label' => 'More', 'link' => self::LINK],
            ['image' => self::KITCHEN, 'title' => 'Two', 'text' => 'A longer text that wraps.'],
            ['image' => self::VIEW, 'title' => 'Three'],
        ]]);

        $this->assertStringContainsString('class="b-cards b-cards--3 b-cards--overlay b-cards--carousel b-cards--split b-cards--plated"', $html);
        // The pane is the slider's overlay, out of the track, printed hidden for the script to show.
        $this->assertMatchesRegularExpression('~webx-slider--overlaid"~', $html);
        $this->assertMatchesRegularExpression('~<div class="webx-slider__overlay" hidden><div class="b-cards__plate">\s*<div class="b-cards__plate-items webx-slider__captions" aria-live="polite">~', $html);
        $this->assertSame(3, substr_count($html, 'class="b-cards__plate-item webx-slider__caption'));
        $this->assertSame(1, substr_count($html, 'webx-slider__caption is-current'));
        $this->assertSame(2, substr_count($html, 'inert aria-hidden="true"'));
        // Every card keeps its words in its slide too: that is what shows without the script.
        $this->assertSame(6, substr_count($html, '<div class="b-cards__body">'));
        $this->assertSame(2, substr_count($html, 'href="/flats/12"'));
        // The lines are in the pane, under the words; no pause button, no controls under the slider.
        $this->assertMatchesRegularExpression('~</div>\s*<div class="b-cards__bars"></div>\s*</div></div>~', $html);
        $this->assertStringContainsString('&quot;paginationIn&quot;:&quot;.b-cards__bars&quot;', $html);
        $this->assertStringContainsString('&quot;pause&quot;:false', $html);
        $this->assertStringNotContainsString('webx-slider__pause', $html);
        $this->assertStringNotContainsString('webx-slider__controls', $html);
        $this->assertStringContainsString('&quot;autoplay&quot;:5000', $html);

        // One card: nothing to choose, no lines.
        $this->app->forgetInstance(Sliders::class);
        $this->assertStringNotContainsString('<div class="b-cards__bars">', $this->render(['view' => 'split', 'pictures' => 'overlay', 'cards' => [['image' => self::HALL, 'title' => 'One']]]));

        $styles = self::rules((string) self::type()['styles']);
        foreach (['--cards-plate-fade: 400ms', '--cards-bar-width: 32px', '--cards-bar-height: 3px', '--cards-bar-gap', '--cards-bar-color', '--cards-bar-current', '--cards-bars-space'] as $knob) {
            $this->assertStringContainsString($knob, $styles['.b-cards--plated']);
        }
        $this->assertStringContainsString('--webx-slider-caption-fade: var(--cards-plate-fade)', $styles['.b-cards--plated .webx-slider']);
        $this->assertStringContainsString('display: none', $styles['.b-cards--plated .webx-slider.is-ready .webx-slider__slide .b-cards__body']);
        $this->assertStringContainsString('position: relative', $styles['.b-cards__plate']);
    }

    /**
     * The rules of a stylesheet by selector, the comments left out.
     *
     * @return array<string, string>
     */
    private static function rules(string $styles): array
    {
        preg_match_all('~([^{}]+)\{([^{}]*)\}~', (string) preg_replace('#/\*.*?\*/#s', '', $styles), $found, PREG_SET_ORDER);
        $rules = [];

        foreach ($found as [, $selector, $body]) {
            // The first rule of a selector, not the one an @supports repeats it in.
            $rules[(string) preg_replace('~\s+~', ' ', trim($selector))] ??= $body;
        }

        return $rules;
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
