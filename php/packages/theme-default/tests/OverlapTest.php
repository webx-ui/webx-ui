<?php

declare(strict_types=1);

namespace WebxUi\ThemeDefault\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * «Slide over the section above» (THEMES §9.8, WIDGETS §14.1): every block of the theme has the
 * switch and the two edge pictures; on, the section's root carries `data-webx-overlap` and its edge
 * stands first inside it; off — the block saved before the fields too — nothing of it is printed.
 * The hero and the text with tiles crop their picture in a frame of their own then, so the edge
 * over their top is not cut; their words are what fades when a section slides over them.
 */
final class OverlapTest extends TestCase
{
    private const array BLOCKS = ['hero', 'text', 'media-text', 'cards', 'cta', 'contact', 'text-tiles'];

    /** @var array<string, array<string, mixed>> */
    private const array VALUES = [
        'contact' => ['show_contacts' => false, 'form' => ''],
        'hero' => ['tone' => 'image'],
    ];

    private const array EDGE = ['url' => '/media/fog.png', 'width' => 1920, 'height' => 220];

    private const array PHONE = ['url' => '/media/fog-phone.png', 'width' => 720, 'height' => 140];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class];
    }

    #[Test]
    public function every_block_has_the_switch_and_the_two_edges_shown_only_when_it_is_on(): void
    {
        foreach (self::BLOCKS as $slug) {
            $fields = array_column(self::type($slug)['schema'], null, 'id');

            $this->assertSame('wx-switch', $fields['overlap']['type'] ?? null, $slug);
            $this->assertFalse($fields['overlap']['default'], $slug);
            foreach (['overlap_edge', 'overlap_edge_narrow'] as $id) {
                $this->assertSame('wx-media', $fields[$id]['type'] ?? null, "{$slug}: {$id}");
                $this->assertSame(['when' => 'overlap', 'is' => true], $fields[$id]['visible'] ?? null, "{$slug}: {$id}");
            }
            $this->assertFalse(self::type($slug)['sample']['overlap'], $slug);
        }
    }

    #[Test]
    public function off_or_saved_before_nothing_of_it_is_printed(): void
    {
        foreach (self::BLOCKS as $slug) {
            foreach ([$this->render($slug), $this->render($slug, saved: true)] as $html) {
                $this->assertStringNotContainsString('data-webx-overlap ', $html.' ', $slug);
                $this->assertStringNotContainsString('data-webx-overlap>', $html, $slug);
                $this->assertStringNotContainsString('webx-overlap__edge', $html, $slug);
                $this->assertStringNotContainsString('__media', $html, $slug);
            }
        }
    }

    #[Test]
    public function on_the_root_is_marked_and_the_edge_is_its_first_child(): void
    {
        foreach (self::BLOCKS as $slug) {
            $html = $this->render($slug, ['overlap' => true, 'overlap_edge' => self::EDGE, 'overlap_edge_narrow' => self::PHONE]);

            $this->assertMatchesRegularExpression(
                '~<section [^>]*data-wx-block="'.$slug.'" data-webx-overlap>\s*<picture class="webx-overlap__edge" aria-hidden="true"><source media="\(max-width: 40rem\)" srcset="/media/fog-phone\.png"~',
                $html,
                $slug,
            );
            $this->assertSame(1, substr_count($html, 'data-webx-overlap>'), $slug);

            // On, with no picture chosen yet: the section slides on its own top.
            $bare = $this->render($slug, ['overlap' => true]);
            $this->assertStringContainsString('data-webx-overlap>', $bare, $slug);
            $this->assertStringNotContainsString('webx-overlap__edge', $bare, $slug);
        }
    }

    #[Test]
    public function the_hero_and_the_tiles_crop_their_picture_in_a_frame_of_their_own_when_on(): void
    {
        foreach (['hero' => 'b-hero', 'text-tiles' => 'b-text-tiles'] as $slug => $block) {
            $html = $this->render($slug, ['overlap' => true, 'overlap_edge' => self::EDGE]);

            $this->assertMatchesRegularExpression(
                '~<div class="'.$block.'__media">\s*<img class="'.$block.'__picture"[^>]*>\s*<div class="'.$block.'__shade"></div>\s*</div>~',
                $html,
                $slug,
            );
            $styles = (string) self::type($slug)['styles'];
            $this->assertStringContainsString(".{$block}[data-webx-overlap] {\n    overflow: visible;", $styles, $slug);
            $this->assertStringContainsString(".{$block}__media {", $styles, $slug);
        }
    }

    #[Test]
    public function the_words_of_the_hero_and_the_tiles_fade_under_a_section_that_slides_over_them(): void
    {
        $this->assertMatchesRegularExpression('~class="b-hero__inner" data-webx-overlap-fade~', $this->render('hero'));
        $this->assertMatchesRegularExpression('~class="b-text-tiles__inner" data-webx-overlap-fade~', $this->render('text-tiles'));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(string $slug, array $values = [], bool $saved = false): string
    {
        $type = self::type($slug);
        $data = array_fill_keys(array_column($type['schema'], 'id'), null);
        $data = [...$data, ...$type['sample'], ...(self::VALUES[$slug] ?? []), ...$values];

        if (array_key_exists('image', $data)) {
            $data['image'] = ['url' => '/media/picture.jpg', 'alt' => '', 'width' => 1200, 'height' => 800];
        }
        // Saved before the fields: they are not there at all.
        if ($saved) {
            unset($data['overlap'], $data['overlap_edge'], $data['overlap_edge_narrow']);
        }

        return Blade::render((string) $type['template'], $data, deleteCachedView: true);
    }

    /** @return array<string, mixed> */
    private static function type(string $slug): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__)."/blocks/{$slug}.json"), true, 512, JSON_THROW_ON_ERROR);
    }
}
