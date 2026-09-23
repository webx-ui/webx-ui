<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Calls\Call;
use WebxUi\Mcp\Grants\Grant;

/**
 * The call log as the tab reads it: rows with people's names, and the filters the log can
 * answer.
 */
final class McpCallsEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_log_is_behind_the_audit_permission(): void
    {
        $this->actingAs($this->admin('me@example.test'), 'cms');

        $this->getJson('/api/cms/auth/mcp-calls')->assertForbidden();

        $auditor = $this->admin('auditor@example.test');
        $auditor->roles()->attach($this->role('auditors', ['admins.audit']));

        $this->actingAs($auditor, 'cms');

        $this->getJson('/api/cms/auth/mcp-calls')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function rows_name_the_person_and_the_client_and_the_filters_are_what_is_in_the_log(): void
    {
        $anna = $this->admin('anna@example.test');
        $anna->update(['name' => 'Anna']);

        $grant = Grant::query()->create([
            'cms_user_id' => $anna->getKey(),
            'oauth_client_id' => '9d2f6c1e-0a7b-4c3d-8e5f-1a2b3c4d5e6f',
            'client_name' => 'Claude',
            'redirect_host' => 'claude.ai',
            'consent_version' => '2026-09-21',
            'created_at' => Carbon::now(),
        ]);

        $this->record($anna->getKey(), 'pages_list', ['grant_id' => $grant->getKey(), 'created_at' => Carbon::parse('2026-09-21 10:00:00')]);
        $this->record($anna->getKey(), 'pages_create', ['dry_run' => true, 'arguments' => "{\n    \"title\": \"About\"\n}", 'created_at' => Carbon::parse('2026-09-21 10:01:00')]);
        $this->record(null, 'blog_list_articles', ['created_at' => Carbon::parse('2026-09-21 10:02:00')]);
        // Somebody deleted since: the row outlives them, with the id and no name.
        $this->record(99, 'pages_delete', ['ok' => false, 'error' => 'No such page.', 'created_at' => Carbon::parse('2026-09-21 10:03:00')]);

        $this->actingAs($this->admin('me@example.test', super: true), 'cms');

        $response = $this->getJson('/api/cms/auth/mcp-calls')->assertOk();

        $response
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 4)
            // Newest first.
            ->assertJsonPath('data.0.tool', 'pages_delete')
            ->assertJsonPath('data.0.user', ['id' => 99, 'name' => null])
            ->assertJsonPath('data.0.ok', false)
            ->assertJsonPath('data.0.error', 'No such page.')
            ->assertJsonPath('data.1.user', null)
            ->assertJsonPath('data.1.client', null)
            ->assertJsonPath('data.2.dry_run', true)
            ->assertJsonPath('data.2.arguments', "{\n    \"title\": \"About\"\n}")
            ->assertJsonPath('data.3.user', ['id' => $anna->getKey(), 'name' => 'Anna'])
            ->assertJsonPath('data.3.client', 'Claude')
            ->assertJsonPath('data.3.at', '2026-09-21T10:00:00+00:00');

        $this->assertSame(
            [['id' => $anna->getKey(), 'name' => 'Anna'], ['id' => 99, 'name' => null], ['id' => null, 'name' => null]],
            $response->json('filters.users'),
        );
        $this->assertSame(
            ['blog_list_articles', 'pages_create', 'pages_delete', 'pages_list'],
            $response->json('filters.tools'),
        );
    }

    #[Test]
    public function it_narrows_by_person_by_tool_and_by_outcome(): void
    {
        $anna = $this->admin('anna@example.test');

        $this->record($anna->getKey(), 'pages_list');
        $this->record($anna->getKey(), 'pages_create', ['dry_run' => true]);
        $this->record($anna->getKey(), 'pages_create', ['ok' => false, 'error' => 'Refused.']);
        $this->record(null, 'pages_list');

        $this->actingAs($this->admin('me@example.test', super: true), 'cms');

        $this->getJson('/api/cms/auth/mcp-calls?user='.$anna->getKey())->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/cms/auth/mcp-calls?user=none')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/cms/auth/mcp-calls?tool=pages_create')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/cms/auth/mcp-calls?outcome=ok')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/cms/auth/mcp-calls?outcome=failed')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/cms/auth/mcp-calls?outcome=dry')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/cms/auth/mcp-calls?user='.$anna->getKey().'&tool=pages_list')->assertOk()->assertJsonCount(1, 'data');

        $this->getJson('/api/cms/auth/mcp-calls?user=anna')->assertStatus(422);
        $this->getJson('/api/cms/auth/mcp-calls?outcome=maybe')->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function record(?int $user, string $tool, array $overrides = []): Call
    {
        return Call::query()->create($overrides + [
            'cms_user_id' => $user,
            'tool' => $tool,
            'ok' => true,
            'dry_run' => false,
            'duration_ms' => 12,
            'created_at' => Carbon::now(),
        ]);
    }
}
