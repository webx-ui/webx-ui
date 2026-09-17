<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Settings\Models\Setting;

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
}
