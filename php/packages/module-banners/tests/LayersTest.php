<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Panel\BannerForm;

/**
 * The layers of a banner (the layers spec): what a save keeps and refuses, what a card hands the
 * template — words with their fallback language and the banner's fields put in, the stack and the
 * reading order — and what `<x-webx-banners>` prints.
 */
final class LayersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->picture();
    }

    /**
     * @param  array<string, string>|string  $text
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function text(array|string $text, float $x = 5, float $y = 5, array $extra = []): array
    {
        return [
            'type' => 'text',
            'text' => is_string($text) ? ['en' => $text] : $text,
            'frames' => ['desktop' => ['x' => $x, 'y' => $y, 'w' => 40, 'size' => 3]],
            ...$extra,
        ];
    }

    #[Test]
    public function a_save_keeps_every_layer_whole_with_its_defaults(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('places/hero/banners'), ['values' => [
            'image' => ['path' => 'media/ab/cd/wide.jpg'],
            'layers' => [
                self::text(['en' => '{title}', 'ru' => ''], extra: ['id' => 'head1', 'tag' => 'h2']),
                ['type' => 'buttons'],
            ],
        ]]);

        $response->assertCreated();

        $layers = $response->json('data.values.layers');
        $this->assertCount(2, $layers);
        $this->assertSame('head1', $layers[0]['id']);
        $this->assertSame(['en' => '{title}'], $layers[0]['text'], 'an empty language is not kept');
        $this->assertSame('h2', $layers[0]['tag']);
        $this->assertSame(['desktop', 'mobile'], array_keys($layers[0]['frames']));
        $this->assertNull($layers[0]['frames']['mobile']);
        $this->assertSame('tl', $layers[0]['frames']['desktop']['origin']);
        $this->assertSame(1, $layers[1]['frames']['desktop']['gap']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{4,12}$/', $layers[1]['id'], 'a layer without an id gets one');
    }

    #[Test]
    public function a_refusal_lands_under_the_layer_and_its_field(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api('places/hero/banners'), ['values' => [
            'image' => ['path' => 'media/ab/cd/wide.jpg'],
            'layers' => [
                self::text('Fine'),
                self::text('Too far', 400, extra: ['tag' => 'h1', 'style' => ['color' => 'red']]),
                ['type' => 'button', 'text' => ['en' => 'Go']],
                ['type' => 'buttons'],
                ['type' => 'buttons'],
                ['type' => 'circle'],
            ],
        ]])->assertStatus(422)->assertJsonValidationErrors([
            'layers.1.frames.desktop.x',
            'layers.1.tag',
            'layers.1.style.color',
            'layers.2.link',
            'layers.4',
            'layers.5.type',
        ])->assertJsonMissingValidationErrors(['layers.0.text', 'layers.3']);

        $this->assertSame(0, Banner::query()->count());
    }

    #[Test]
    public function a_text_layer_without_words_and_a_button_without_anything_are_dropped(): void
    {
        $banner = $this->banner();

        $saved = $this->app->make(BannerForm::class)->save($banner, ['layers' => [
            self::text(['en' => '  ']),
            ['type' => 'button'],
            self::text('Kept'),
        ]]);

        $this->assertCount(1, $saved->layerRows());
        $this->assertSame(['en' => 'Kept'], $saved->layerRows()[0]['text']);
    }

    #[Test]
    public function a_layer_speaks_the_page_language_else_the_default_else_the_first_written(): void
    {
        $this->banner(title: null, attributes: [
            'title' => ['en' => 'Sale', 'ru' => 'Распродажа'],
            'layers' => [
                self::text(['ru' => 'Только по-русски'], extra: ['id' => 'onlyru']),
                self::text(['en' => 'English', 'ru' => 'Русский'], 5, 20, ['id' => 'both']),
                self::text(['en' => 'Only English'], 5, 40, ['id' => 'onlyen']),
            ],
        ]);

        $en = $this->texts(banners('hero')->locale('en')->first());
        $ru = $this->texts(banners('hero')->locale('ru')->first());

        $this->assertSame(['onlyru' => 'Только по-русски', 'both' => 'English', 'onlyen' => 'Only English'], $en, 'the first written');
        $this->assertSame(['onlyru' => 'Только по-русски', 'both' => 'Русский', 'onlyen' => 'Only English'], $ru, 'the default language');
    }

    #[Test]
    public function the_fields_of_the_banner_are_put_in_in_the_language_of_the_page(): void
    {
        $this->banner(title: null, attributes: [
            'title' => ['en' => 'Spring sale', 'ru' => 'Весенняя распродажа'],
            'text' => ['en' => "Two\nlines"],
            'layers' => [
                self::text('{title} — up to 50%', extra: ['id' => 'head']),
                self::text('{text}', 5, 20, ['id' => 'body']),
                self::text('{titel} {2026}', 5, 40, ['id' => 'typo']),
            ],
        ]);

        $this->assertSame(
            ['head' => 'Spring sale — up to 50%', 'body' => "Two\nlines", 'typo' => '{titel} {2026}'],
            $this->texts(banners('hero')->locale('en')->first()),
        );

        // Russian has no text: `{text}` is empty there, and the layer with nothing left drops out.
        $this->assertSame(
            ['head' => 'Весенняя распродажа — up to 50%', 'typo' => '{titel} {2026}'],
            $this->texts(banners('hero')->locale('ru')->first()),
        );
    }

    #[Test]
    public function the_words_of_layers_do_not_decide_where_a_banner_is_seen(): void
    {
        $banner = $this->banner(attributes: ['layers' => [self::text(['ru' => 'Слой'])]]);

        $this->assertSame([], banners('hero')->locale('ru')->get(), 'the title is English only (decision 12)');
        $this->assertSame($banner->id, banners('hero')->locale('en')->first()['id'] ?? null);
    }

    #[Test]
    public function the_top_of_the_list_is_in_front_and_the_reading_goes_top_down(): void
    {
        $this->banner(attributes: ['layers' => [
            self::text('Lower', 5, 30, ['id' => 'lower']),
            self::text('Upper right', 60, 5, ['id' => 'right']),
            self::text('Upper left', 5, 5, ['id' => 'left']),
        ]]);

        $layers = banners('hero')->first()['layers'];

        $this->assertSame(['left', 'right', 'lower'], array_column($layers, 'id'));
        $this->assertSame([1, 2, 3], array_column($layers, 'z'));
    }

    #[Test]
    public function the_geometry_is_custom_properties_of_numbers_for_both_layouts(): void
    {
        $this->banner(attributes: ['layers' => [[
            'type' => 'text',
            'text' => ['en' => 'Hi'],
            'style' => ['color' => 'token:accent', 'font' => 'heading', 'shadow' => true],
            'frames' => [
                'desktop' => ['x' => 5.22, 'y' => 1, 'w' => 40, 'size' => 1.71, 'origin' => 'tr'],
                'mobile' => ['x' => 10, 'y' => 50, 'w' => 80, 'size' => 6, 'hidden' => true],
            ],
            'motion' => ['in' => 'rise', 'delay' => 200],
        ]]]);

        $layer = banners('hero')->first()['layers'][0];

        foreach (['--x:5.22', '--size:1.71', '--ox:-100%', '--m-x:10', '--m-size:6', '--m-display:none', '--display:block', '--color:var(--site-color-accent)', '--font:var(--site-font-heading)', '--delay:200ms', 'z-index:1'] as $part) {
            $this->assertStringContainsString($part, $layer['style']);
        }

        $this->assertSame('is-shadow is-in-rise', $layer['class']);
    }

    #[Test]
    public function the_buttons_layer_carries_the_banners_buttons_and_drops_without_them(): void
    {
        $this->banner(attributes: [
            'buttons' => [['label' => ['en' => 'Prices'], 'link' => ['target' => 'url', 'url' => 'https://example.org/prices']]],
            'layers' => [['type' => 'buttons', 'id' => 'btns']],
        ]);

        $layers = banners('hero')->locale('en')->first()['layers'];
        $this->assertSame('Prices', $layers[0]['buttons'][0]['label']);

        Banner::query()->update(['buttons' => null]);

        $this->assertSame([], banners('hero')->locale('en')->first()['layers']);
    }

    #[Test]
    public function a_place_without_layers_hands_none_to_its_template(): void
    {
        $this->banner('promo', attributes: ['layers' => [self::text('Hidden')]]);

        $this->assertSame([], banners('promo')->first()['layers']);
    }

    #[Test]
    public function the_component_prints_the_stage_once_styled_and_the_words_escaped(): void
    {
        $this->banner(attributes: ['layers' => [self::text('</style><script>alert(1)</script>', extra: ['tag' => 'h2'])]]);
        $this->banner(attributes: ['layers' => [self::text('Second')]]);

        $html = Blade::render('<x-webx-banners place="hero" class="b-hero__media" />');

        $this->assertStringContainsString('class="webx-banners webx-banners--slider b-hero__media"', $html);
        $this->assertSame(1, substr_count($html, '<style data-webx-banners>'), 'the stylesheet is printed once a page');
        $this->assertStringContainsString('@media (max-width: 767px)', $html);
        $this->assertStringContainsString('.webx-banner[data-breakpoint="768"] .webx-banner__layer', $html);
        $this->assertStringContainsString('--img:2.6667', $html);
        $this->assertStringContainsString('&lt;/style&gt;&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame(2, substr_count($html, 'class="webx-banner__stage"'));
    }

    #[Test]
    public function an_empty_or_unknown_place_prints_nothing(): void
    {
        $this->assertSame('', trim(Blade::render('<x-webx-banners place="hero" />')));
        $this->assertSame('', trim(Blade::render('<x-webx-banners place="nowhere" />')));
    }

    /**
     * @param  array<string, mixed>|null  $card
     * @return array<string, string>
     */
    private function texts(?array $card): array
    {
        $this->assertIsArray($card);

        return array_column($card['layers'], 'text', 'id');
    }
}
