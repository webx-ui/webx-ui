<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Settings\ContentRules;
use WebxUi\Settings\DataShortcodes;
use WebxUi\Settings\Settings;

final class DataShortcodesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_panel_defines_shortcodes_that_read_a_setting_or_hold_their_value(): void
    {
        app(Settings::class)->save([
            'general.project-name' => ['ru' => 'Глобекс'],
            DataShortcodes::KEY => [
                ['name' => 'phone', 'key' => '', 'value' => ['ru' => '+44 20 7946 0958']],
                ['name' => 'email', 'key' => '', 'value' => ['ru' => 'hello@example.com']],
                ['name' => 'brand', 'key' => 'general.project-name', 'value' => null],
                ['name' => 'address', 'key' => '', 'value' => ['ru' => "1 High St\nLondon"]],
                ['name' => 'Not A Name', 'key' => '', 'value' => ['ru' => 'x']],
            ],
        ]);

        $shortcodes = app(Shortcodes::class);

        $this->assertSame(['address', 'brand', 'email', 'phone'], array_keys($shortcodes->all()));
        $this->assertSame('settings', $shortcodes->get('phone')?->origin);
        $this->assertSame('general.project-name', $shortcodes->get('brand')?->description);

        $this->assertSame('Call <a href="tel:+442079460958">+44 20 7946 0958</a>', $shortcodes->html('Call [phone]'));
        $this->assertSame('<a href="mailto:hello@example.com">hello@example.com</a>', $shortcodes->html('[email]'));
        $this->assertSame('1 High St<br>'."\n".'London', $shortcodes->html('[address]'));
        $this->assertSame('Глобекс', $shortcodes->html('[brand]'));

        // Plain text is the value, never the link.
        $this->assertSame('Call +44 20 7946 0958 or hello@example.com', $shortcodes->plain('Call [phone] or [email]'));

        // Arguments: a phone in another format, a value without its link.
        $this->assertSame('+442079460958', $shortcodes->plain('[phone format=intl]'));
        $this->assertSame('442079460958', $shortcodes->plain('[phone format=digits]'));
        $this->assertSame('+44 20 7946 0958', $shortcodes->html('[phone link=no]'));

        // A changed setting changes every text that says the shortcode.
        app(Settings::class)->save(['general.project-name' => ['ru' => 'Инитех']]);
        $this->assertSame('Инитех', $shortcodes->plain('[brand]'));
    }

    #[Test]
    public function the_switch_decides_what_a_row_prints_and_an_old_row_reads_its_key(): void
    {
        app(Settings::class)->save([
            'general.project-name' => ['ru' => 'Глобекс'],
            DataShortcodes::KEY => [
                // Switched to its own value: the key left behind from before is not read.
                ['name' => 'own', 'source' => 'value', 'key' => 'general.project-name', 'value' => ['ru' => 'Свой']],
                ['name' => 'read', 'source' => 'setting', 'key' => 'general.project-name', 'value' => ['ru' => 'Свой']],
                // Saved before there was a switch: a key filled in means the setting.
                ['name' => 'old', 'key' => 'general.project-name', 'value' => null],
            ],
        ]);

        $shortcodes = app(Shortcodes::class);

        $this->assertSame('Свой Глобекс Глобекс', $shortcodes->plain('[own] [read] [old]'));
        $this->assertNull($shortcodes->get('own')?->description);
        $this->assertSame(['setting', 'value', 'value'], [
            DataShortcodes::source(['key' => 'a.b']),
            DataShortcodes::source(['key' => '']),
            DataShortcodes::source(['source' => 'value', 'key' => 'a.b']),
        ]);
    }

    #[Test]
    public function the_panel_sees_every_row_s_source_and_cannot_save_a_setting_that_does_not_exist(): void
    {
        app(Settings::class)->save([DataShortcodes::KEY => [
            ['name' => 'brand', 'key' => 'general.project-name', 'value' => null],
            ['name' => 'phone', 'key' => '', 'value' => ['ru' => '+44 20 7946 0958']],
        ]]);

        $rows = $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/settings')
            ->assertOk()
            ->json('data.values')[DataShortcodes::KEY];

        $this->assertSame(['setting', 'value'], array_column($rows, 'source'));

        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => [DataShortcodes::KEY => [
                ['name' => 'brand', 'source' => 'setting', 'key' => 'general.project-name', 'value' => null],
                ['name' => 'typo', 'source' => 'setting', 'key' => 'contacts.phnoe', 'value' => null],
                ['name' => 'blank', 'source' => 'setting', 'key' => '', 'value' => null],
                ['name' => 'own', 'source' => 'value', 'key' => 'no.such', 'value' => ['ru' => 'x']],
            ]]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', [
                DataShortcodes::KEY.'.1.key' => ['There is no setting contacts.phnoe.'],
                DataShortcodes::KEY.'.2.key' => ['Choose the setting to read.'],
            ]);
    }

    #[Test]
    public function a_value_is_printed_as_text_when_it_is_neither_a_phone_nor_an_e_mail(): void
    {
        $this->assertSame('phone', DataShortcodes::kind('(555) 010-0199'));
        $this->assertSame('email', DataShortcodes::kind('a.b@example.org'));
        $this->assertSame('text', DataShortcodes::kind('2026'));
        $this->assertSame('text', DataShortcodes::kind('Mon–Fri 9–18'));
        $this->assertSame('&lt;b&gt;', DataShortcodes::html('<b>'));
    }

    #[Test]
    public function the_content_rules_name_the_shortcodes_for_agents(): void
    {
        app(Settings::class)->save([DataShortcodes::KEY => [['name' => 'phone', 'key' => '', 'value' => ['ru' => '+44 20 7946 0958']]]]);

        $rules = app(ContentRules::class)->toArray();

        $this->assertStringContainsString('[phone]', $rules['shortcodes']['rule']);
        $this->assertSame([['name' => 'phone', 'plain' => '+44 20 7946 0958', 'description' => null]], $rules['shortcodes']['list']);
    }
}
