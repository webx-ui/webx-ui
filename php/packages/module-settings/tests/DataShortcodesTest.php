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
