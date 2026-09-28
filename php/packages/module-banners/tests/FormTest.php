<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Banners\Models\Banner;

/**
 * The editor of a banner through the panel's API (§5.4, §5.6): the section in the manifest, the
 * shapes of the form, and where every refusal lands.
 */
final class FormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->picture();
    }

    #[Test]
    public function the_section_is_one_entry_at_the_top_of_the_menu(): void
    {
        /** @var array<int, array<string, mixed>> $modules */
        $modules = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');
        $banners = null;

        foreach ($modules as $module) {
            if (($module['id'] ?? null) === 'banners') {
                $banners = $module;
            }
        }

        $this->assertIsArray($banners);
        $this->assertNull($banners['group'] ?? null);
        $this->assertSame('image', $banners['icon'] ?? null);
        $this->assertSame(['banners.view', 'banners.manage'], $banners['permissions']);
    }

    #[Test]
    public function the_screen_has_the_variants_of_the_config_as_options(): void
    {
        $screen = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/banners.form')->assertOk()->json('data');

        $this->assertStringContainsString('"value":"secondary"', (string) json_encode($screen));
        $this->assertTrue(Screens::has(Banner::SCREEN));
    }

    #[Test]
    public function a_new_banner_is_made_off_at_the_end_of_its_place_in_the_shape_of_the_spec(): void
    {
        $first = $this->banner();

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('places/hero/banners'), ['values' => [
            'image' => ['path' => 'media/ab/cd/wide.jpg', 'url' => 'https://stale.example/x.jpg'],
            'title' => ['en' => 'Spring', 'ru' => 'Весна'],
            'text' => ['en' => '  Lines  '],
            'buttons' => [
                ['label' => ['en' => 'Go'], 'link' => self::url('/go'), 'variant' => 'secondary'],
                ['label' => ['en' => ''], 'link' => null],
            ],
        ]])->assertCreated();

        $this->assertSame(['banner', 'values'], array_keys((array) $response->json('data')));
        $this->assertSame(['id', 'place', 'title', 'enabled', 'deleted_at'], array_keys((array) $response->json('data.banner')));
        $response->assertJsonPath('data.banner.place', 'hero')
            ->assertJsonPath('data.banner.title', 'Spring')
            ->assertJsonPath('data.banner.enabled', false)
            ->assertJsonPath('data.values.image', ['path' => 'media/ab/cd/wide.jpg'])
            ->assertJsonPath('data.values.title', ['en' => 'Spring', 'ru' => 'Весна'])
            ->assertJsonPath('data.values.text', ['en' => 'Lines'])
            ->assertJsonPath('data.values.enabled', false)
            ->assertJsonPath('data.values.image_mobile', null)
            ->assertJsonPath('data.values.video', null);

        $buttons = (array) $response->json('data.values.buttons');
        $this->assertCount(1, $buttons, 'an empty row is dropped');
        $this->assertSame(['en' => 'Go'], $buttons[0]['label']);
        $this->assertSame('/go', $buttons[0]['link']['url']);
        $this->assertSame('secondary', $buttons[0]['variant']);

        $banner = Banner::query()->findOrFail($response->json('data.banner.id'));
        $this->assertGreaterThan($first->position, $banner->position);
    }

    #[Test]
    public function a_banner_opens_with_its_values_and_its_fields_of_the_project(): void
    {
        $banner = $this->banner(attributes: ['buttons' => [['label' => ['en' => 'Go'], 'link' => self::url('/go'), 'variant' => 'primary']]]);
        $banner->mergeExtra(['badge' => 'New'])->save();

        $this->actingAs($this->editor(['banners.view']), 'cms')->getJson($this->api($banner->id))
            ->assertOk()
            ->assertJsonPath('data.banner.id', $banner->id)
            ->assertJsonPath('data.banner.place', 'hero')
            ->assertJsonPath('data.values.badge', 'New')
            ->assertJsonPath('data.values.buttons.0.variant', 'primary')
            ->assertJsonPath('data.values.enabled', true);
    }

    #[Test]
    public function without_a_picture_the_refusal_is_under_image_video_or_not(): void
    {
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api('places/hero/banners'), ['values' => ['title' => ['en' => 'X']]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'A banner needs a picture.');

        $this->actingAs($editor, 'cms')->postJson($this->api('places/hero/banners'), ['values' => ['video' => ['path' => 'media/ab/cd/clip.mp4']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image'])
            ->assertJsonMissingValidationErrors(['video']);

        $banner = $this->banner();

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => ['image' => null]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => ['enabled' => false]])
            ->assertOk(); // a save that does not send the picture keeps it
    }

    #[Test]
    public function the_wrong_kind_of_file_is_refused_under_its_field(): void
    {
        $this->picture('media/ab/cd/clip.mp4', 'video/mp4');
        $banner = $this->banner();
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => ['video' => ['path' => 'media/ab/cd/wide.jpg']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => ['image_mobile' => ['path' => 'media/ab/cd/clip.mp4']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_mobile']);

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => ['video' => ['path' => 'media/ab/cd/clip.mp4']]])
            ->assertOk()
            ->assertJsonPath('data.values.video', ['path' => 'media/ab/cd/clip.mp4']);
    }

    #[Test]
    public function a_fourth_button_is_refused_under_buttons(): void
    {
        $banner = $this->banner();
        $button = ['label' => ['en' => 'Go'], 'link' => self::url('/go'), 'variant' => 'primary'];

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($banner->id), ['values' => ['buttons' => [$button, $button, $button, $button]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buttons']);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($banner->id), ['values' => ['buttons' => [$button, $button, $button, ['label' => [], 'link' => null]]]])
            ->assertOk(); // three and an empty row
    }

    #[Test]
    public function a_button_row_is_refused_under_its_own_field_numbered_as_the_editor_sees_it(): void
    {
        $banner = $this->banner();

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($banner->id), ['values' => ['buttons' => [
            ['label' => ['en' => ''], 'link' => null],
            ['label' => ['en' => 'No link'], 'link' => null, 'variant' => 'primary'],
            ['label' => ['en' => 'Script'], 'link' => self::url('javascript:alert(1)'), 'variant' => 'primary'],
            ['label' => ['en' => 'Ghost'], 'link' => self::url('/ghost'), 'variant' => 'ghost'],
        ]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buttons.1.link', 'buttons.2.link', 'buttons.3.variant'])
            ->assertJsonMissingValidationErrors(['buttons.0.link'])
            ->assertJsonPath('errors', fn (array $errors): bool => str_contains($errors['buttons.3.variant'][0], 'primary, secondary, link'));

        $this->assertNull($banner->refresh()->buttons, 'nothing written');
    }

    #[Test]
    public function a_variant_taken_out_of_the_config_does_not_lock_the_banner(): void
    {
        $banner = $this->banner(attributes: ['buttons' => [
            ['label' => ['en' => 'Old'], 'link' => self::url('/old'), 'variant' => 'ghost'],
        ]]);

        $values = (array) $this->actingAs($this->editor(), 'cms')->getJson($this->api($banner->id))->json('data.values');
        $values['buttons'][] = ['label' => ['en' => 'New'], 'link' => self::url('/new'), 'variant' => 'secondary'];

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($banner->id), ['values' => $values])
            ->assertOk()
            ->assertJsonPath('data.values.buttons.0.variant', 'ghost')
            ->assertJsonPath('data.values.buttons.1.variant', 'secondary');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($banner->id), ['values' => ['buttons' => [
            ...$values['buttons'],
            ['label' => ['en' => 'Also ghost'], 'link' => self::url('/also'), 'variant' => 'ghost'],
        ]]])->assertOk(); // a variant this banner already has is its to keep on another button
    }

    #[Test]
    public function a_new_place_puts_the_banner_at_its_end_and_an_unknown_one_is_refused(): void
    {
        $promo = $this->banner('promo');
        $banner = $this->banner('hero');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => [], 'place' => 'promo'])
            ->assertOk()
            ->assertJsonPath('data.banner.place', 'promo');

        $this->assertSame([$promo->id, $banner->id], array_column(banners('promo')->get(), 'id'));

        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => [], 'place' => 'nowhere'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['place']);

        $position = $banner->refresh()->position;
        $this->actingAs($editor, 'cms')->putJson($this->api($banner->id), ['values' => ['enabled' => true], 'place' => 'promo'])->assertOk();
        $this->assertSame($position, $banner->refresh()->position, 'the same place is not a move');
    }

    #[Test]
    public function the_bin_and_back(): void
    {
        $banner = $this->banner();
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api($banner->id))->assertNoContent();
        $this->assertSame([], banners('hero')->get());
        $this->actingAs($editor, 'cms')->getJson($this->api($banner->id))->assertNotFound();

        $this->actingAs($editor, 'cms')->postJson($this->api($banner->id.'/restore'))
            ->assertOk()
            ->assertJsonPath('data.id', $banner->id)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertCount(1, banners('hero')->get());
    }

    #[Test]
    public function a_translated_word_is_merged_by_language(): void
    {
        $banner = $this->banner(attributes: ['title' => ['en' => 'Spring', 'ru' => 'Весна']]);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($banner->id), ['values' => ['title' => ['en' => 'Summer']]])->assertOk();
        $this->assertSame(['en' => 'Summer', 'ru' => 'Весна'], $banner->refresh()->getTranslations('title'));

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($banner->id), ['values' => ['title' => ['ru' => '']]])->assertOk();
        $this->assertSame(['en' => 'Summer'], $banner->refresh()->getTranslations('title'));
    }
}
