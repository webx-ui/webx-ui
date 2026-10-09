<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Settings\Models\Setting;
use WebxUi\Settings\Settings;

final class McpToolsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function invoke(string $name, array $arguments = []): mixed
    {
        return ($this->app->make(ToolRegistry::class)->tool("settings_{$name}")->tool->handler)($arguments);
    }

    #[Test]
    public function list_describes_the_keys_the_screen_declares(): void
    {
        $this->assertSame([
            ['key' => 'general.project-name', 'label' => 'Project name', 'type' => 'wx-input', 'localized' => true],
            ['key' => 'branding.logo', 'label' => 'Logo', 'type' => 'wx-media', 'localized' => false],
            ['key' => 'branding.mark', 'label' => 'Mark', 'type' => 'wx-media', 'localized' => false],
            ['key' => 'contacts.phones', 'label' => 'Phone numbers', 'type' => 'wx-repeater', 'localized' => false],
            ['key' => 'contacts.emails', 'label' => 'E-mail addresses', 'type' => 'wx-repeater', 'localized' => false],
            ['key' => 'contacts.addresses', 'label' => 'Addresses', 'type' => 'wx-repeater', 'localized' => false],
            ['key' => 'contacts.hours', 'label' => 'Opening hours', 'type' => 'wx-repeater', 'localized' => false],
            ['key' => 'contacts.hours-exceptions', 'label' => 'Special dates', 'type' => 'wx-repeater', 'localized' => false],
            ['key' => 'contacts.timezone', 'label' => 'Time zone', 'type' => 'wx-input', 'localized' => false],
            ['key' => 'contacts.messengers', 'label' => 'Chats', 'type' => 'wx-repeater', 'localized' => false],
            ['key' => 'contacts.socials', 'label' => 'Social networks', 'type' => 'wx-repeater', 'localized' => false],
            ['key' => 'shortcodes.data', 'label' => 'Shortcodes', 'type' => 'wx-repeater', 'localized' => false],
            ['key' => 'content.tone', 'label' => 'Tone of voice', 'type' => 'wx-textarea', 'localized' => false],
            ['key' => 'content.donts', 'label' => "Don'ts", 'type' => 'wx-textarea', 'localized' => false],
            ['key' => 'content.notes', 'label' => 'Notes for the agent', 'type' => 'wx-textarea', 'localized' => false],
        ], $this->invoke('list'));
    }

    #[Test]
    public function set_refuses_an_unknown_key_and_a_bad_value_and_honours_dry_run(): void
    {
        $unknown = $this->invoke('set', ['key' => 'nope', 'value' => 1]);
        $this->assertFalse($unknown['ok']);

        $bad = $this->invoke('set', ['key' => 'general.project-name', 'value' => ['ru' => str_repeat('x', 2001)]]);
        $this->assertFalse($bad['ok']);
        $this->assertArrayHasKey('general.project-name', $bad['errors']);

        $dry = $this->invoke('set', ['key' => 'general.project-name', 'value' => ['ru' => 'Акме'], 'dry_run' => true]);
        $this->assertSame(['ok' => true, 'would_change' => true, 'key' => 'general.project-name', 'applied' => false], $dry);
        $this->assertSame(0, Setting::query()->count());

        $applied = $this->invoke('set', ['key' => 'general.project-name', 'value' => ['ru' => 'Акме']]);
        $this->assertTrue($applied['applied']);
        $this->assertSame(['ru' => 'Акме'], $this->invoke('get', ['key' => 'general.project-name'])['stored']);
        $this->assertSame('Акме', $this->invoke('get', ['key' => 'general.project-name'])['resolved']);
    }

    /**
     * One field of every kind an agent was unable to empty: a text field kept the word "null",
     * the rest refused it as the wrong shape.
     *
     * @return iterable<string, array{array<string, mixed>, mixed, mixed}>
     */
    public static function clearable(): iterable
    {
        foreach ([null, 'null'] as $clear) {
            $as = $clear === null ? 'null' : 'the string null';

            yield "wx-input, {$as}" => [['type' => 'wx-input'], 'A title', $clear];
            yield "wx-textarea, {$as}" => [['type' => 'wx-textarea'], "Two\nlines", $clear];
            yield "localized wx-input, {$as}" => [['type' => 'wx-input', 'localized' => true], ['ru' => 'Акме', 'uk' => 'Акме'], $clear];
            yield "wx-media, {$as}" => [['type' => 'wx-media', 'props' => ['accept' => 'image']], [1], $clear];
            yield "wx-repeater, {$as}" => [[
                'type' => 'wx-repeater',
                'children' => [['id' => 'test-row-url', 'type' => 'wx-input', 'name' => 'url']],
            ], [['url' => 'https://example.test']], $clear];
            yield "wx-link, {$as}" => [['type' => 'wx-link'], ['target' => 'url', 'url' => '/about'], $clear];
            yield "wx-input-number, {$as}" => [['type' => 'wx-input-number'], 7, $clear];
            yield "wx-slider, {$as}" => [['type' => 'wx-slider'], 40, $clear];
            yield "wx-switch, {$as}" => [['type' => 'wx-switch'], true, $clear];
            yield "wx-select, {$as}" => [[
                'type' => 'wx-select',
                'props' => ['options' => [['label' => 'One', 'value' => 'one'], ['label' => 'Two', 'value' => 'two']]],
            ], 'two', $clear];
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    #[Test]
    #[DataProvider('clearable')]
    public function set_with_null_clears_a_field_of_any_type_back_to_the_default(array $node, mixed $value, mixed $clear): void
    {
        $this->app->make(ScreenRegistry::class)->extend(Settings::SCREEN, [[
            'op' => 'add',
            'target' => 'tabs',
            'node' => [
                'id' => 'test-tab',
                'type' => 'wx-tab',
                'children' => [['id' => 'test-field', 'name' => 'test.field', ...$node]],
            ],
        ]]);

        $set = $this->invoke('set', ['key' => 'test.field', 'value' => $value]);
        $this->assertTrue($set['applied'] ?? false, (string) json_encode($set));
        $this->assertNotNull($this->invoke('get', ['key' => 'test.field'])['stored']);

        $cleared = $this->invoke('set', ['key' => 'test.field', 'value' => $clear]);
        $this->assertSame(['ok' => true, 'would_change' => true, 'key' => 'test.field', 'applied' => true], $cleared);
        $this->assertFalse(Setting::query()->where('key', 'test.field')->exists());
        $this->assertSame('fallback', settings('test.field', 'fallback'));

        // Clearing what is already clear changes nothing.
        $this->assertFalse($this->invoke('set', ['key' => 'test.field', 'value' => $clear])['would_change']);
    }

    #[Test]
    public function a_localized_setting_empties_one_language_with_null_and_falls_back_once_none_is_left(): void
    {
        $this->invoke('set', ['key' => 'general.project-name', 'value' => ['ru' => 'Акме', 'uk' => 'Акме UA']]);

        $this->invoke('set', ['key' => 'general.project-name', 'value' => ['uk' => 'null']]);
        $this->assertSame(['ru' => 'Акме'], $this->invoke('get', ['key' => 'general.project-name'])['stored']);

        $this->invoke('set', ['key' => 'general.project-name', 'value' => ['ru' => null]]);
        $this->assertFalse(Setting::query()->where('key', 'general.project-name')->exists());
        $this->assertSame('fallback', settings('general.project-name', 'fallback'));
    }
}
