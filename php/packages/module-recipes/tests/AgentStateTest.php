<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

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
 * names the revision it read, and has to while somebody has the recipe open in the panel.
 */
final class AgentStateTest extends TestCase
{
    #[Test]
    public function nobody_editing_lets_a_publication_through_without_a_revision(): void
    {
        $recipe = $this->recipe('porridge', published: false);

        $this->agent('recipes_publish', ['recipe' => $recipe->getKey()])->assertOk();

        $this->assertTrue($recipe->refresh()->isPublished());
    }

    #[Test]
    public function somebody_editing_asks_for_the_revision_and_names_who(): void
    {
        $recipe = $this->recipe('porridge', published: false);
        $owner = $this->editor();
        $this->app->make(Presence::class)->touch($recipe, (int) $owner->getKey(), 'Owner');

        $this->agent('recipes_publish', ['recipe' => $recipe->getKey()])
            ->assertHasErrors(['Owner', 'recipes_get', 'force: true']);
        $this->assertFalse($recipe->refresh()->isPublished());

        $this->agent('recipes_publish', ['recipe' => $recipe->getKey(), 'revision' => 'stale'])
            ->assertHasErrors(['changed since you read it']);
        $this->assertFalse($recipe->refresh()->isPublished());

        $revision = $this->content($this->agent('recipes_get', ['recipe' => $recipe->getKey()]))['revision'];

        $this->agent('recipes_publish', ['recipe' => $recipe->getKey(), 'revision' => $revision])->assertOk();
        $this->assertTrue($recipe->refresh()->isPublished());
    }

    #[Test]
    public function the_panel_does_not_publish_under_a_revision_that_is_gone(): void
    {
        $recipe = $this->recipe('porridge', published: false);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($recipe->getKey().'/publish'), ['revision' => 'stale'])
            ->assertStatus(409);

        $this->assertFalse($recipe->refresh()->isPublished());
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
