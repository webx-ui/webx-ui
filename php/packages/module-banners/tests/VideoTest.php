<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Places;

/**
 * The video of a banner (§4, §5.2 of the banners spec): the widgets' backdrop over the picture,
 * one for phones of its own, a poster of its own — and a banner without them as it always was.
 */
final class VideoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->picture('media/ab/cd/wide.jpg');
        $this->picture('media/ab/cd/tall.jpg');
        $this->picture('media/ab/cd/still.jpg');
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');
        $this->picture('media/ab/cd/upright.mp4', 'video/mp4');
    }

    #[Test]
    public function a_banner_with_a_picture_only_prints_as_before(): void
    {
        $this->banner(attributes: ['image_mobile' => ['path' => 'media/ab/cd/tall.jpg']]);

        $html = $this->hero();

        $this->assertStringNotContainsString('data-webx-video', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertMatchesRegularExpression('~<source media="\(max-width: 767px\)" srcset="[^"]*tall\.jpg[^"]*"~', $html);
        $this->assertMatchesRegularExpression('~<img class="webx-banner__image" src="[^"]*wide\.jpg[^"]*"[^>]*loading="eager" fetchpriority="high"~', $html);
    }

    #[Test]
    public function the_video_is_the_widgets_backdrop_over_the_picture(): void
    {
        $this->banner(attributes: ['video' => ['path' => 'media/ab/cd/clip.mp4']]);

        $html = $this->hero();

        // One backdrop, for wide screens: the place does not play the video on phones.
        $this->assertSame(1, substr_count($html, 'data-webx-video-background'));
        $this->assertStringContainsString('class="webx-video webx-video--background webx-banner__video webx-banner__video--wide"', $html);
        $this->assertMatchesRegularExpression('~<video class="webx-video__background" muted loop playsinline preload="none"~', $html);
        $this->assertMatchesRegularExpression('~<source src="[^"]*clip\.mp4[^"]*"\s+type="video/mp4"~', $html);
        $this->assertStringContainsString('class="webx-video__pause is-paused"', $html, 'the pause button of WCAG 2.2.2');

        // The picture is the poster: the backdrop has none of its own to load a second time.
        $this->assertStringNotContainsString('webx-video__poster', $html);
        $this->assertStringNotContainsString('poster=', $html);
        $this->assertMatchesRegularExpression('~<img class="webx-banner__image" src="[^"]*wide\.jpg[^"]*"[^>]*fetchpriority="high"~', $html);

        // The package no longer plays it itself.
        $this->assertStringNotContainsString('data-src', $html);
        $this->assertStringNotContainsString('.webx-banner__video[data-src]', $html);
        $this->assertStringContainsString('.webx-banner[data-breakpoint="768"] .webx-banner__picture > .webx-banner__video--wide', $html);
    }

    #[Test]
    public function video_on_mobile_plays_the_one_video_at_every_width(): void
    {
        config()->set('webx-banners.places.hero.options', ['video_on_mobile' => true]);
        $this->banner(attributes: ['video' => ['path' => 'media/ab/cd/clip.mp4']]);

        $html = $this->hero();

        $this->assertSame(1, substr_count($html, 'data-webx-video-background'));
        $this->assertStringContainsString('class="webx-video webx-video--background webx-banner__video"', $html);
    }

    #[Test]
    public function a_phone_plays_its_own_video(): void
    {
        $this->banner(attributes: [
            'video' => ['path' => 'media/ab/cd/clip.mp4'],
            'video_mobile' => ['path' => 'media/ab/cd/upright.mp4'],
        ]);

        $html = $this->hero();

        $this->assertSame(2, substr_count($html, 'data-webx-video-background'));
        $this->assertMatchesRegularExpression('~webx-banner__video--wide"[^>]*>.*?clip\.mp4~s', $html);
        $this->assertMatchesRegularExpression('~webx-banner__video--narrow"[^>]*>.*?upright\.mp4~s', $html);
        $this->assertStringContainsString('.webx-banner[data-breakpoint="768"] .webx-banner__picture > .webx-banner__video--narrow', $html);
    }

    #[Test]
    public function a_phone_video_alone_plays_on_phones_only(): void
    {
        $this->banner(attributes: ['video_mobile' => ['path' => 'media/ab/cd/upright.mp4']]);

        $html = $this->hero();

        $this->assertSame(1, substr_count($html, 'data-webx-video-background'));
        $this->assertStringContainsString('webx-banner__video--narrow"', $html);
        $this->assertStringNotContainsString('webx-banner__video--wide"', $html);
    }

    #[Test]
    public function a_place_without_phones_has_no_phone_video(): void
    {
        config()->set('webx-banners.places.hero.options', ['breakpoint' => 0]);
        $this->banner(attributes: [
            'video' => ['path' => 'media/ab/cd/clip.mp4'],
            'video_mobile' => ['path' => 'media/ab/cd/upright.mp4'],
        ]);

        $html = $this->hero();

        $this->assertSame(1, substr_count($html, 'data-webx-video-background'));
        $this->assertStringNotContainsString('upright.mp4', $html);
        $this->assertStringNotContainsString('<source media=', $html);
    }

    #[Test]
    public function the_poster_stands_where_the_video_plays_and_the_picture_where_it_does_not(): void
    {
        $this->banner(attributes: [
            'image' => ['path' => 'media/ab/cd/wide.jpg', 'alt' => ['en' => 'Flowers']],
            'video' => ['path' => 'media/ab/cd/clip.mp4'],
            'poster' => ['path' => 'media/ab/cd/still.jpg'],
        ]);

        $html = $this->hero();

        // Wide screens: the poster, eager, with the picture's words. A phone plays nothing here
        // and sees the picture.
        $this->assertMatchesRegularExpression('~<img class="webx-banner__image" src="[^"]*still\.jpg[^"]*" alt="Flowers" width="1920" height="720" loading="eager" fetchpriority="high">~', $html);
        $this->assertMatchesRegularExpression('~<source media="\(max-width: 767px\)" srcset="[^"]*wide\.jpg[^"]*"~', $html);
    }

    #[Test]
    public function a_poster_serves_a_phone_video_too_and_the_phone_picture_wins_over_it(): void
    {
        $this->banner(attributes: [
            'video' => ['path' => 'media/ab/cd/clip.mp4'],
            'video_mobile' => ['path' => 'media/ab/cd/upright.mp4'],
            'poster' => ['path' => 'media/ab/cd/still.jpg'],
        ]);

        $html = $this->hero();
        $this->assertStringContainsString('still.jpg', $html);
        $this->assertStringNotContainsString('<source media=', $html, 'the poster at both widths: one file');
        $this->assertStringNotContainsString('wide.jpg', $html);

        Banner::query()->firstOrFail()->update(['image_mobile' => ['path' => 'media/ab/cd/tall.jpg']]);

        $this->assertMatchesRegularExpression('~<source media="\(max-width: 767px\)" srcset="[^"]*tall\.jpg[^"]*"~', $this->hero());
    }

    #[Test]
    public function a_poster_without_a_video_is_not_shown(): void
    {
        $this->banner(attributes: ['poster' => ['path' => 'media/ab/cd/still.jpg']]);

        $html = $this->hero();

        $this->assertStringNotContainsString('still.jpg', $html);
        $this->assertStringContainsString('wide.jpg', $html);
    }

    #[Test]
    public function the_card_carries_the_phone_video_and_the_poster(): void
    {
        $this->banner(attributes: [
            'video_mobile' => ['path' => 'media/ab/cd/upright.mp4'],
            'poster' => ['path' => 'media/ab/cd/still.jpg', 'alt' => ['en' => 'A still']],
        ]);

        $card = banners('hero')->first();

        $this->assertIsArray($card);
        $this->assertNull($card['video']);
        $this->assertSame(['url', 'mime'], array_keys((array) $card['video_mobile']));
        $this->assertSame('A still', $card['poster']['alt']);
        $this->assertSame(1920, $card['poster']['width']);
    }

    #[Test]
    public function the_editor_saves_both_and_refuses_the_wrong_kind_of_file(): void
    {
        $editor = $this->editor();
        $banner = $this->banner();

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => ['poster' => ['path' => 'media/ab/cd/clip.mp4']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['poster']);

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => ['video_mobile' => ['path' => 'media/ab/cd/still.jpg']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['video_mobile']);

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => [
            'video_mobile' => ['path' => 'media/ab/cd/upright.mp4'],
            'poster' => ['path' => 'media/ab/cd/still.jpg'],
        ]])
            ->assertOk()
            ->assertJsonPath('data.values.video_mobile', ['path' => 'media/ab/cd/upright.mp4'])
            ->assertJsonPath('data.values.poster', ['path' => 'media/ab/cd/still.jpg']);

        // The list marks a banner that plays a video, the phone's included.
        $this->actingAs($editor, 'cms')->getJson($this->api('places/hero/banners'))->assertJsonPath('data.0.video', true);

        $this->actingAs($editor, 'cms')->getJson($this->api($banner->id))
            ->assertJsonPath('data.values.video', null)
            ->assertJsonPath('data.values.video_mobile', ['path' => 'media/ab/cd/upright.mp4']);
    }

    #[Test]
    public function a_phone_video_needs_the_picture_too(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api('places/hero/banners'), ['values' => ['video_mobile' => ['path' => 'media/ab/cd/upright.mp4']]])
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', __('webx-banners::errors.video-without-image'));
    }

    #[Test]
    public function the_hero_takes_a_main_heading(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api('places/hero/banners'), ['values' => [
            'image' => ['path' => 'media/ab/cd/wide.jpg'],
            'enabled' => true,
            'layers' => [['type' => 'text', 'tag' => 'h1', 'text' => ['en' => 'The house by the lake'], 'frames' => ['desktop' => ['x' => 6, 'y' => 6, 'w' => 45, 'size' => 4], 'mobile' => null]]],
        ]])->assertCreated();

        $this->assertMatchesRegularExpression('~<h1 class="webx-banner__layer webx-banner__layer--text[^"]*"[^>]*><span class="webx-banner__inner">The house by the lake</span></h1>~', $this->hero());

        // Only there: the site's list keeps its h2 at the top, a slider elsewhere has several slides.
        $this->assertArrayNotHasKey('h1', $this->app->make(Places::class)->tags('promo'));
        $this->assertArrayNotHasKey('h1', $this->app->make(Places::class)->tags(null));
    }

    private function hero(): string
    {
        return Blade::render('<x-webx-banners place="hero" />');
    }
}
