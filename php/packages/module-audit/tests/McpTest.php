<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Fixes\ReplaceHost;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\RunAuditStage;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The audit by its other door (§9): an agent starts a run, reads it check by check and fixes
 * with a dry run first.
 */
final class McpTest extends TestCase
{
    private FakeContentSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->source = new FakeContentSource;
        $this->source->records = ['1' => [
            'label' => 'Delivery',
            'published' => true,
            'fields' => ['body' => '<a href="https://dev.shop.example.com/sale">sale</a>'],
        ]];
        $this->app->make(AuditContentSources::class)->register($this->source);
    }

    #[Test]
    public function the_module_offers_its_tools_and_the_catalogue_of_checks(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['audit_run', 'audit_status', 'audit_issues', 'audit_pages', 'audit_page_get', 'audit_hosts', 'audit_fix'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('audit')),
        );

        $catalogue = null;

        foreach ($registry->resources() as $resource) {
            if ($resource->uri === 'audit://checks') {
                $catalogue = ($resource->handler)();
            }
        }

        $byId = array_column($catalogue['checks'] ?? [], null, 'id');
        $this->assertSame([ReplaceHost::ID], $byId['hosts.dev_content']['fixes'] ?? null);
        $this->assertNotSame('', $byId['hosts.dev_content']['why'] ?? '');
    }

    #[Test]
    public function an_agent_starts_a_run_and_fixes_with_a_dry_run_first(): void
    {
        Bus::fake([RunAuditStage::class]);
        $admin = $this->admin(['audit.view', 'audit.run', 'audit.manage']);

        $this->agent('run', ['scope' => 'quick'], $admin)->assertOk();
        Bus::assertDispatched(RunAuditStage::class);

        $this->artisan('webx:audit:run', ['--quick' => true]);

        $checks = $this->content($this->agent('issues', [], $admin))['checks'] ?? [];
        $this->assertContains('hosts.dev_content', array_column($checks, 'id'));

        $issue = AuditIssue::query()->where('check', 'hosts.dev_content')->latest('id')->firstOrFail();

        $offers = $this->content($this->agent('fix', ['issue' => $issue->id], $admin));
        $this->assertSame(ReplaceHost::ID, $offers['fixes'][0]['id'] ?? null);

        $dry = $this->content($this->agent('fix', ['issue' => $issue->id, 'fix' => ReplaceHost::ID, 'dry_run' => true], $admin));
        $this->assertFalse($dry['applied'] ?? null);
        $this->assertSame([], $this->source->replaced);

        $this->agent('fix', ['issue' => $issue->id, 'fix' => ReplaceHost::ID], $admin)->assertOk();
        $this->assertCount(1, $this->source->replaced);
    }

    #[Test]
    public function the_hosts_put_the_stands_first(): void
    {
        $this->artisan('webx:audit:run', ['--quick' => true]);

        $hosts = $this->content($this->agent('hosts', [], $this->admin(['audit.view'])))['hosts'] ?? [];

        $this->assertSame(['host' => 'dev.shop.example.com', 'class' => 'dev', 'links' => 0, 'pages' => 0, 'fields' => 1], $hosts[0] ?? null);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('audit_'.$tool));

        return WebxServer::actingAs($as, 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
