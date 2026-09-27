<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Services\ServicesServiceProvider;
use WebxUi\Team\Models\Member;

/**
 * A site without `module-services` (decision 3): no "Services" field on the form, no relation in
 * the block's choice, no service links on a card — and a save that still names services treats
 * the name as any field the screen does not have, leaving the rows those services left behind.
 */
final class WithoutServicesTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter(
            parent::getPackageProviders($app),
            static fn (string $provider): bool => $provider !== ServicesServiceProvider::class,
        ));
    }

    #[Test]
    public function the_services_field_is_not_on_the_screen(): void
    {
        $root = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/team.form')->assertOk()->json('data.root');

        $ids = [];
        array_walk_recursive($root, static function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'id') {
                $ids[] = $value;
            }
        });

        $this->assertNotContains('services', $ids);
        $this->assertContains('socials', $ids);
    }

    #[Test]
    public function a_save_naming_services_is_a_field_nobody_has_and_leaves_their_rows_alone(): void
    {
        $member = $this->member('Anna');

        // A service that was related before the module went: the row waits for it to come back.
        DB::table(Relations::TABLE)->insert([
            'owner_type' => Member::TYPE,
            'owner_id' => $member->id,
            'role' => Member::SERVICES,
            'target_type' => 'service',
            'target_id' => 7,
            'position' => 0,
        ]);

        $response = $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($member->id), ['values' => ['services' => [1, 2], 'job_title' => ['en' => 'Doctor']]])
            ->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertArrayNotHasKey('services', $values);
        $this->assertSame('Doctor', $values['job_title']['en'] ?? null);
        $this->assertArrayNotHasKey('services', (array) $member->refresh()->extraRaw());
        $this->assertSame([7], $member->relatedIds(Member::SERVICES));

        $this->assertSame([], team()->first()['service_links'] ?? null);
    }

    #[Test]
    public function the_block_is_offered_no_relation(): void
    {
        $response = $this->actingAs($this->editor(['team.view', 'pages.view']), 'cms')->getJson('/api/cms/collections')->assertOk();

        $team = null;

        foreach ((array) $response->json('data') as $source) {
            if (is_array($source) && ($source['key'] ?? null) === 'team') {
                $team = $source;
            }
        }

        $this->assertIsArray($team);
        $this->assertSame([], $team['relations'] ?? null);
        $this->assertArrayHasKey('categories', $team);
        $this->assertNull($team['categories']);
    }

    #[Test]
    public function an_agent_is_offered_no_services_and_told_why_when_it_names_one(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertArrayNotHasKey('services', $registry->tool('team_create')->tool->inputSchema['properties'] ?? []);

        $response = WebxServer::actingAs($this->editor(), 'cms')
            ->tool(new RegistryTool($registry->tool('team_create')), ['name' => 'Anna', 'services' => [1]]);

        $response->assertHasErrors(['no services module']);
        $this->assertSame(0, Member::withTrashed()->count());
    }
}
