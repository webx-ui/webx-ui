<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Editing\Presence;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A change of state — a publication above all — acts on whatever the draft holds now, so an agent
 * names the revision it read, and has to while somebody has the service open in the panel.
 */
final class AgentStateTest extends TestCase
{
    #[Test]
    public function nobody_editing_lets_a_publication_through_without_a_revision(): void
    {
        $service = $this->service('implants', published: false);

        $this->agent('services_publish', ['service' => $service->getKey()])->assertOk();

        $this->assertTrue($service->refresh()->isPublished());
    }

    #[Test]
    public function somebody_editing_asks_for_the_revision_and_names_who(): void
    {
        $service = $this->service('implants', published: false);
        $owner = $this->editor();
        $this->app->make(Presence::class)->touch($service, (int) $owner->getKey(), 'Owner');

        $this->agent('services_publish', ['service' => $service->getKey()])
            ->assertHasErrors(['Owner', 'services_get', 'force: true']);
        $this->assertFalse($service->refresh()->isPublished());

        $this->agent('services_publish', ['service' => $service->getKey(), 'revision' => 'stale'])
            ->assertHasErrors(['changed since you read it']);
        $this->assertFalse($service->refresh()->isPublished());

        $revision = $this->content($this->agent('services_get', ['service' => $service->getKey()]))['revision'];

        $this->agent('services_publish', ['service' => $service->getKey(), 'revision' => $revision])->assertOk();
        $this->assertTrue($service->refresh()->isPublished());
    }

    #[Test]
    public function the_panel_does_not_publish_under_a_revision_that_is_gone(): void
    {
        $service = $this->service('implants', published: false);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($service->getKey().'/publish'), ['revision' => 'stale'])
            ->assertStatus(409);

        $this->assertFalse($service->refresh()->isPublished());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertOk()->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
