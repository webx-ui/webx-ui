<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Settings\ContentRules;

final class ContentRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $resources = array_values(array_filter(
            $this->app->make(ToolRegistry::class)->resources(),
            static fn (McpResource $resource): bool => $resource->uri === ContentRules::URI,
        ));

        $this->assertCount(1, $resources);

        return ($resources[0]->handler)([]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function set(array $arguments): array
    {
        return ($this->app->make(ToolRegistry::class)->tool('settings_set')->tool->handler)($arguments);
    }

    #[Test]
    public function empty_rules_still_carry_the_languages_of_the_site(): void
    {
        $rules = $this->read();

        $this->assertSame('settings://content-rules', ContentRules::URI);
        $this->assertSame(['ru', 'uk'], array_column($rules['languages'], 'code'));
        $this->assertSame([true, false], array_column($rules['languages'], 'primary'));
        $this->assertSame('ru', $rules['primary']);
        $this->assertNull($rules['tone']);
        $this->assertSame([], $rules['donts']);
        $this->assertNull($rules['notes']);
        $this->assertSame([], $rules['more']);
        $this->assertTrue($rules['empty']);
    }

    #[Test]
    public function the_rules_are_settings_written_like_any_other_and_a_patched_rule_comes_out_in_more(): void
    {
        Screens::extend('settings.content', [
            [
                'op' => 'add',
                'target' => 'content-card',
                'node' => ['id' => 'audience', 'type' => 'wx-input', 'name' => 'content.audience', 'label' => 'Audience'],
            ],
        ]);

        $this->assertTrue($this->set(['key' => 'content.tone', 'value' => '  Friendly, short sentences.  '])['applied']);
        $this->assertTrue($this->set(['key' => 'content.donts', 'value' => "No prices\r\n\r\n  No superlatives \n"])['applied']);
        $this->assertTrue($this->set(['key' => 'content.audience', 'value' => 'Small clinics'])['applied']);

        $rules = $this->read();

        $this->assertSame('Friendly, short sentences.', $rules['tone']);
        $this->assertSame(['No prices', 'No superlatives'], $rules['donts']);
        $this->assertNull($rules['notes']);
        $this->assertSame([['key' => 'content.audience', 'label' => 'Audience', 'value' => 'Small clinics']], $rules['more']);
        $this->assertFalse($rules['empty']);
    }

    #[Test]
    public function an_editor_who_may_only_look_cannot_change_the_rules(): void
    {
        $this->actingAs($this->editor(['settings.view']), 'cms')
            ->putJson('/api/cms/settings/content', ['values' => ['content.tone' => 'Loud']])
            ->assertForbidden();

        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings/content', ['values' => ['content.tone' => 'Calm']])
            ->assertOk();

        $this->assertSame('Calm', $this->read()['tone']);
    }

    #[Test]
    public function the_rules_have_their_own_screen_and_endpoint_apart_from_the_settings(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings/content', ['values' => ['content.tone' => 'Calm', 'site.name' => 'Not here']])
            ->assertOk()
            ->assertJsonPath('data.values', ['content.tone' => 'Calm']);

        $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/settings')
            ->assertOk()
            ->assertJsonMissingPath('data.values.content.tone');

        $this->actingAs($this->editor(['settings.view']), 'cms')
            ->getJson('/api/cms/settings/content')
            ->assertOk()
            ->assertJsonPath('data.values', ['content.tone' => 'Calm']);
    }

    #[Test]
    public function the_server_tells_the_agent_to_read_the_rules_first(): void
    {
        $server = $this->app->make(WebxServer::class, ['transport' => new FakeTransporter]);
        $server->start();

        $this->assertStringContainsString('read `settings://content-rules`', $server->createContext()->instructions);
    }
}
