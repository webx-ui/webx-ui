<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Settings\Models\Setting;

/**
 * What the panel wears, read from the settings.
 *
 * The pictures are stored here the way `module-media` resolves them — with an address in the
 * value — because that module is not installed in these tests and there is nothing to resolve
 * them with. What is being checked is the half that lives here: a value with an address
 * becomes a logo, a value without one is not a logo at all, and neither requires the file
 * library to be present for the settings to answer.
 */
final class BrandingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_branding_tab_is_part_of_the_screen(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/screens/settings.index', ['X-Webx-Locale' => 'ru'])
            ->assertOk()
            ->assertJsonPath('data.root.0.children.1.id', 'branding')
            ->assertJsonPath('data.root.0.children.1.label', 'Брендинг')
            ->assertJsonPath('data.root.0.children.1.children.0.children.0.name', 'branding.logo')
            ->assertJsonPath('data.root.0.children.1.children.0.children.0.type', 'wx-media')
            ->assertJsonPath('data.root.0.children.1.children.0.children.1.name', 'branding.mark');
    }

    #[Test]
    public function the_panel_is_called_what_the_client_called_the_project(): void
    {
        Setting::query()->create([
            'key' => 'general.project-name',
            'value' => ['ru' => 'Акме', 'uk' => 'Акме Україна'],
        ]);

        $this->actingAs($this->editor(), 'cms');

        $this->getJson('/api/cms/manifest', ['X-Webx-Locale' => 'ru'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Акме');

        // The name is localized like any other content value, so two administrators reading
        // the panel in two languages see the name each of them wrote.
        $this->getJson('/api/cms/manifest', ['X-Webx-Locale' => 'uk'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Акме Україна');
    }

    #[Test]
    public function an_unnamed_project_leaves_the_deployed_title_alone(): void
    {
        Setting::query()->create(['key' => 'general.project-name', 'value' => ['ru' => '']]);

        $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.title', 'WebX UI')
            ->assertJsonPath('data.branding.logo', null)
            ->assertJsonPath('data.branding.mark', null);
    }

    #[Test]
    public function a_logo_and_a_mark_travel_in_the_manifest(): void
    {
        Setting::query()->create([
            'key' => 'branding.logo',
            'value' => ['path' => 'brand/logo.svg', 'url' => 'https://acme.test/logo.svg', 'width' => 240, 'height' => 48],
        ]);
        Setting::query()->create([
            'key' => 'branding.mark',
            'value' => ['path' => 'brand/mark.svg', 'url' => 'https://acme.test/mark.svg'],
        ]);

        $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.branding.logo.url', 'https://acme.test/logo.svg')
            ->assertJsonPath('data.branding.logo.width', 240)
            ->assertJsonPath('data.branding.logo.height', 48)
            ->assertJsonPath('data.branding.mark.url', 'https://acme.test/mark.svg')
            ->assertJsonPath('data.branding.mark.height', null);
    }

    #[Test]
    public function a_picture_nothing_can_turn_into_an_address_is_not_a_logo(): void
    {
        // What a bare `wx-media` value looks like when the file library is not installed: a
        // key into a store nobody can read. The corner keeps its name rather than a broken
        // picture.
        Setting::query()->create(['key' => 'branding.logo', 'value' => ['path' => 'brand/logo.svg']]);

        $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.branding.logo', null);
    }
}
