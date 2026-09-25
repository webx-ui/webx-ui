<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Events\Demo\EventsDemo;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Services\ServicesServiceProvider;

/**
 * A site without `module-services` (decision 10): no "Services" field on the form, a save that
 * still names services neither fails nor touches the rows those services left behind, and the
 * rest works as it does anywhere.
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
        $root = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/events.form')->assertOk()->json('data.root');

        $ids = [];
        array_walk_recursive($root, static function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'id') {
                $ids[] = $value;
            }
        });

        $this->assertNotContains('services', $ids);
        $this->assertContains('categories', $ids);
    }

    #[Test]
    public function a_save_naming_services_does_not_fail_and_leaves_their_rows_alone(): void
    {
        $event = $this->event('class', '2030-10-12 10:00:00');

        // A service that was related before the module went: the row waits for it to come back.
        DB::table(Relations::TABLE)->insert([
            'owner_type' => 'event',
            'owner_id' => $event->id,
            'role' => 'services',
            'target_type' => 'service',
            'target_id' => 7,
            'position' => 0,
        ]);

        $response = $this->actingAs($this->editor(['events.view', 'events.manage']), 'cms')
            ->putJson($this->api($event->id), ['values' => ['services' => [1, 2], 'lead' => ['en' => 'Quick.']]])
            ->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertArrayNotHasKey('services', $values);
        $this->assertArrayNotHasKey('services', (array) $event->refresh()->extraRaw());
        $this->assertSame([7], $event->relatedIds('services'));

        $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk()->assertJsonPath('filters.services', null);

        $this->get('/events/class')->assertOk();

        // A copy of it does not fail either, and does not carry what nobody can see.
        $this->actingAs($this->editor(), 'cms')->postJson($this->api($event->id.'/duplicate'))->assertCreated();
    }

    #[Test]
    public function the_demo_asks_for_the_library_alone_and_an_agent_is_told_why_it_cannot_link(): void
    {
        $this->assertSame(['media'], $this->app->make(EventsDemo::class)->requires());

        $event = $this->event('class', '2030-10-12 10:00:00');
        $tool = new RegistryTool($this->app->make(ToolRegistry::class)->tool('events_update'));

        WebxServer::actingAs($this->editor(), 'cms')
            ->tool($tool, ['event' => $event->id, 'values' => ['services' => [1]]])
            ->assertHasErrors(['no services module']);
    }
}
