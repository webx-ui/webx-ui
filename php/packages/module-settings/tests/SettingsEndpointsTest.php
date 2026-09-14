<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Settings\Events\SettingsSaved;
use WebxUi\Settings\Models\Setting;

final class SettingsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_screen_is_registered_and_the_module_sits_in_the_system_group(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.screens', ['settings.index'])
            ->assertJsonPath('data.modules.0.id', 'settings')
            ->assertJsonPath('data.modules.0.group', 'system')
            ->assertJsonPath('data.modules.0.title', 'Settings')
            ->assertJsonPath('data.modules.0.meta.screen', 'settings.index')
            ->assertJsonPath('data.modules.1.id', 'admins')
            ->assertJsonPath('data.modules.1.group', 'system');
    }

    #[Test]
    public function the_screen_arrives_translated(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/screens/settings.index', ['X-Webx-Locale' => 'ru'])
            ->assertOk()
            ->assertJsonPath('data.root.0.children.0.id', 'general')
            ->assertJsonPath('data.root.0.children.0.label', 'Общие')
            ->assertJsonPath('data.root.0.children.0.children.0.children.0.name', 'general.project-name')
            ->assertJsonPath('data.root.0.children.0.children.0.children.0.label', 'Название проекта');
    }

    #[Test]
    public function reading_needs_the_view_permission_and_writing_the_manage_one(): void
    {
        $this->actingAs($this->editor(['media.view']), 'cms')
            ->getJson('/api/cms/settings')
            ->assertForbidden();

        $this->actingAs($this->editor(['settings.view']), 'cms')
            ->putJson('/api/cms/settings', ['values' => []])
            ->assertForbidden();
    }

    #[Test]
    public function values_are_saved_by_the_screen_and_read_back(): void
    {
        Event::fake([SettingsSaved::class]);

        // Keys hold literal dots, so the values are read as an array rather than by JSON path.
        $saved = $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => [
                'general.project-name' => ['ru' => 'Акме', 'uk' => 'Акме'],
                'not.described' => 'dropped',
            ]])
            ->assertOk()
            ->json('data.values');

        $this->assertSame(['general.project-name' => ['ru' => 'Акме', 'uk' => 'Акме']], $saved);
        $this->assertSame(1, Setting::query()->count());
        Event::assertDispatched(SettingsSaved::class, static fn (SettingsSaved $event): bool => $event->keys === ['general.project-name']);

        $read = $this->getJson('/api/cms/settings')->assertOk()->json('data.values');

        $this->assertSame('Акме', $read['general.project-name']['uk']);
    }

    #[Test]
    public function a_bad_value_is_a_422_under_its_key_in_the_panel_language(): void
    {
        $errors = $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => [
                'general.project-name' => ['ru' => str_repeat('x', 2001)],
            ]], ['X-Webx-Locale' => 'ru'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['general.project-name'])
            ->json('errors');

        $this->assertStringContainsString('Название проекта', $errors['general.project-name'][0]);
        $this->assertStringContainsString('2000', $errors['general.project-name'][0]);
    }

    #[Test]
    public function a_project_patch_opens_a_key_for_writing(): void
    {
        Screens::extend('settings.index', [
            [
                'op' => 'add',
                'target' => 'general-card',
                'node' => ['id' => 'phone', 'type' => 'wx-input', 'name' => 'contacts.phone', 'label' => 'Phone'],
            ],
        ]);

        $values = $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => ['contacts.phone' => '+380 44 000 00 00']])
            ->assertOk()
            ->json('data.values');

        $this->assertSame('+380 44 000 00 00', $values['contacts.phone']);

        $this->assertSame('+380 44 000 00 00', settings('contacts.phone'));
    }

    #[Test]
    public function the_site_reads_the_current_language_with_fallbacks_and_the_cache_is_refreshed_on_save(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => ['general.project-name' => ['ru' => 'Акме', 'uk' => '']]])
            ->assertOk();

        $this->app->setLocale('uk');
        $this->assertSame('Акме', settings('general.project-name'));
        $this->assertSame('n/a', settings('general.missing', 'n/a'));

        $this->putJson('/api/cms/settings', ['values' => ['general.project-name' => ['ru' => 'Глобекс', 'uk' => 'Глобекс']]])
            ->assertOk();

        $this->assertSame('Глобекс', settings('general.project-name'));
        $this->assertSame(['general.project-name' => 'Глобекс'], settings()->all());
    }

    #[Test]
    public function the_mcp_tools_list_read_and_set_by_the_screen(): void
    {
        $this->artisan('webx:mcp-tools')
            ->expectsOutputToContain('settings_list')
            ->expectsOutputToContain('settings_set')
            ->assertSuccessful();
    }
}
