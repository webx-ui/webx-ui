<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;

/**
 * The parallax of a banner (§4 of the banners spec, §14 of the widgets spec): the picture and the
 * video in a second `webx-banner__picture` that moves, the layers' stage outside it — and, off, a
 * banner printed exactly as before. And what fades when a section slides over the banner (§14.1).
 */
final class ParallaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->picture('media/ab/cd/wide.jpg');
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');
    }

    #[Test]
    public function off_by_default_a_banner_prints_as_before(): void
    {
        $this->banner(attributes: ['video' => ['path' => 'media/ab/cd/clip.mp4'], 'layers' => [self::layer()]]);

        $html = $this->hero();

        $this->assertFalse(banners_layout('hero')['parallax']);
        $this->assertStringNotContainsString('data-webx-parallax', $html);
        $this->assertStringNotContainsString('webx-banner__drift', $html);
        $this->assertSame(1, substr_count($html, 'class="webx-banner__picture"'));
    }

    #[Test]
    public function the_place_moves_the_picture_and_the_video_and_leaves_the_layers_in_the_frame(): void
    {
        config()->set('webx-banners.places.hero.options', ['parallax' => true]);
        $this->banner(attributes: ['video' => ['path' => 'media/ab/cd/clip.mp4'], 'layers' => [self::layer()]]);

        $html = $this->hero();

        $this->assertSame(1, substr_count($html, 'data-webx-parallax'));
        // The outer picture crops; the inner one, with the picture and the video, moves.
        $this->assertMatchesRegularExpression(
            '~<div class="webx-banner__picture">\s*<div class="webx-banner__picture webx-banner__drift" data-webx-parallax>\s*<picture>.*?webx-banner__image.*?</picture>.*?data-webx-video-background.*?</div>\s*</div>\s*</div>\s*<div class="webx-banner__stage" data-webx-overlap-fade>~s',
            $html,
        );
        // The stage is the frame's, not the drift's: what the editor placed stays where it was put.
        $drift = (int) strpos($html, 'data-webx-parallax>');
        $stage = (int) strpos($html, '<div class="webx-banner__stage"');
        $this->assertGreaterThan($drift, $stage);
        $this->assertStringContainsString('Over the sea', substr($html, $stage));
    }

    #[Test]
    public function the_template_says_the_last_word(): void
    {
        $this->banner();

        $this->assertStringContainsString('data-webx-parallax', Blade::render('<x-webx-banners place="hero" :parallax="true" />'));

        config()->set('webx-banners.places.hero.options', ['parallax' => true]);
        $this->assertStringNotContainsString('data-webx-parallax', Blade::render('<x-webx-banners place="hero" :parallax="false" />'));
        $this->assertStringContainsString('data-webx-parallax', $this->hero());
    }

    #[Test]
    public function the_option_is_the_strength_too(): void
    {
        $this->banner();

        // Medium — `true` or by name — is the attribute bare: the markup of a place that had `true` before.
        foreach ([true, 'medium', '1', 'on'] as $option) {
            config()->set('webx-banners.places.hero.options', ['parallax' => $option]);
            $this->assertMatchesRegularExpression('~webx-banner__drift" data-webx-parallax>~', $this->hero(), var_export($option, true));
        }

        foreach (['soft', 'strong', ' Strong '] as $option) {
            config()->set('webx-banners.places.hero.options', ['parallax' => $option]);
            $this->assertStringContainsString('data-webx-parallax="'.strtolower(trim($option)).'"', $this->hero());
        }

        foreach ([false, 'off', '0', '', null] as $option) {
            config()->set('webx-banners.places.hero.options', ['parallax' => $option]);
            $this->assertStringNotContainsString('data-webx-parallax', $this->hero(), var_export($option, true));
        }

        $this->assertStringContainsString('data-webx-parallax="soft"', Blade::render('<x-webx-banners place="hero" parallax="soft" />'));
    }

    #[Test]
    public function a_banner_without_a_picture_has_nothing_to_move(): void
    {
        config()->set('webx-banners.places.notice.options', ['parallax' => true]);
        $this->banner('notice', attributes: ['image' => null]);

        $this->assertStringNotContainsString('data-webx-parallax', Blade::render('<x-webx-banners place="notice" />'));
    }

    #[Test]
    public function the_words_and_the_layers_are_what_fades_under_a_section_that_slides_over_the_banner(): void
    {
        // WIDGETS §14.1: the section after the place pins it and fades what is marked; the picture stays.
        $this->banner(title: 'Words', attributes: ['layers' => []]);
        $this->banner(title: null, attributes: ['layers' => [self::layer()]]);

        $html = $this->hero();

        $this->assertStringContainsString('<div class="webx-banner__content" data-webx-overlap-fade>', $html);
        $this->assertStringContainsString('<div class="webx-banner__stage" data-webx-overlap-fade>', $html);
        $this->assertSame(2, substr_count($html, 'data-webx-overlap-fade'));
    }

    /** @return array<string, mixed> */
    private static function layer(): array
    {
        return [
            'type' => 'text',
            'text' => ['en' => 'Over the sea'],
            'frames' => ['desktop' => ['x' => 5, 'y' => 5, 'w' => 40, 'size' => 3]],
        ];
    }

    private function hero(): string
    {
        return Blade::render('<x-webx-banners place="hero" />');
    }
}
