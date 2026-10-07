<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Settings\Models\Setting;

/**
 * A project's field with a `default`: the switch the panel draws on is on for the site too, and
 * nothing is written for it until somebody changes it.
 */
final class FieldDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Screens::extend('settings.index', [
            [
                'op' => 'add',
                'target' => 'general-card',
                'node' => ['id' => 'popup', 'type' => 'wx-switch', 'name' => 'popup.enabled', 'default' => true],
            ],
            [
                'op' => 'add',
                'target' => 'general-card',
                'node' => ['id' => 'email', 'type' => 'wx-input', 'name' => 'contacts.email', 'props' => ['type' => 'email']],
            ],
        ]);
    }

    #[Test]
    public function nothing_stored_reads_as_the_fields_default_before_the_callers(): void
    {
        $this->assertTrue(settings('popup.enabled'));
        $this->assertTrue(settings('popup.enabled', false));
        $this->assertTrue(settings()->all()['popup.enabled']);

        // A field without a default still answers with what the caller asked for.
        $this->assertSame('n/a', settings('contacts.email', 'n/a'));
    }

    #[Test]
    public function a_save_that_does_not_send_the_field_writes_nothing_for_it(): void
    {
        $values = $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => ['contacts.email' => 'team@example.com']])
            ->assertOk()
            ->json('data.values');

        $this->assertArrayNotHasKey('popup.enabled', $values);
        $this->assertFalse(Setting::query()->where('key', 'popup.enabled')->exists());
        $this->assertTrue(settings('popup.enabled'));
    }

    #[Test]
    public function turned_off_it_stays_off(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => ['popup.enabled' => false]])
            ->assertOk();

        $this->assertFalse(settings('popup.enabled'));
    }

    #[Test]
    public function an_address_that_is_not_one_is_refused(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => ['contacts.email' => 'not-an-email']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contacts.email']);
    }
}
