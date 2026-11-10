<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Scopes;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Tests\Fixtures\Scoped;

/**
 * A project's slug is brought to its shape rather than refused (#125): whatever is typed — in the
 * panel, by an agent, by a direct save — becomes `[a-z0-9-]`, and only a slug nothing is left of
 * or one already taken is refused.
 */
final class ProjectSlugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Locale::fromCode('ru', ['is_default' => true, 'sort' => 0])->save();
        $this->app->make(Locales::class)->forget();
    }

    #[Test]
    public function the_panel_makes_a_slug_of_what_was_typed(): void
    {
        $this->actingAs($this->member(['tasks.view', 'tasks.manage']), 'cms');

        $id = (int) $this->postJson('/api/cms/tasks/projects', ['slug' => 'Тормозные Колодки', 'title' => 'Pads'])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame('tormoznye-kolodki', Project::query()->findOrFail($id)->slug);

        $this->putJson("/api/cms/tasks/projects/{$id}", ['slug' => 'Brake Pads & Discs'])->assertOk();
        $this->assertSame('brake-pads-discs', Project::query()->findOrFail($id)->slug);

        // Taken once brought: refused.
        $this->postJson('/api/cms/tasks/projects', ['slug' => 'BRAKE pads discs', 'title' => 'Again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        // Nothing left of it: refused on both doors, never saved as an empty slug.
        $this->postJson('/api/cms/tasks/projects', ['slug' => '!!!', 'title' => 'Signs'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
        $this->putJson("/api/cms/tasks/projects/{$id}", ['slug' => '!!!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        $this->assertSame('brake-pads-discs', Project::query()->whereKey($id)->value('slug'));
    }

    #[Test]
    public function an_agent_and_a_direct_save_get_the_same_slug(): void
    {
        $person = $this->member(['tasks.view', 'tasks.manage']);
        $person->withAccessToken(new Scoped([Scopes::OAUTH]));

        $this->tool('tasks_projects_create', ['slug' => 'Тормозные колодки', 'title' => 'Pads'], $person)->assertOk();
        $this->assertTrue(Project::query()->where('slug', 'tormoznye-kolodki')->exists());

        $this->tool('tasks_projects_update', ['project' => 'tormoznye-kolodki', 'slug' => 'Новый магазин'], $person)->assertOk();
        $this->assertSame(['novyy-magazin'], Project::query()->pluck('slug')->all());

        $this->tool('tasks_projects_create', ['slug' => 'Novyy Magazin', 'title' => 'Again'], $person)->assertHasErrors(['slug']);

        $this->assertSame('ozhidayut-otveta', Project::query()->create(['slug' => 'Ожидают ответа', 'title' => 'X'])->slug);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function tool(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as, 'cms')->tool($bound, $arguments);
    }
}
