<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Routing\Models\Route;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * A dry run is the write, rolled back: an address that is taken is refused by both. What the tools
 * do not take is refused, not dropped; the bin can be undone and emptied by an agent too.
 */
final class McpDryRunTest extends TestCase
{
    #[Test]
    public function a_taken_address_is_refused_by_the_dry_run_too(): void
    {
        $this->category('implants');

        $this->agent('services_create', ['title' => 'Implants', 'dry_run' => true])->assertHasErrors(['already taken']);

        $dry = $this->content($this->agent('services_create', ['title' => 'Crowns', 'dry_run' => true]));

        $this->assertTrue($dry['dry_run']);
        $this->assertNull($dry['service']['id']);
        $this->assertSame('/services/crowns', $dry['service']['urls']['en']['path']);
        $this->assertSame(0, Service::query()->withTrashed()->count());
        $this->assertSame(1, Route::query()->count());
    }

    #[Test]
    public function a_category_dry_run_is_refused_on_a_taken_address_and_shows_the_one_it_would_have(): void
    {
        $this->service('crowns');
        $implants = $this->category('implants');

        $this->agent('service_categories_update', ['category' => $implants->id, 'values' => ['slug' => ['en' => 'crowns']], 'dry_run' => true])
            ->assertHasErrors(['slug']);
        $this->agent('service_categories_create', ['title' => 'Crowns', 'dry_run' => true])->assertHasErrors(['slug']);

        $dry = $this->content($this->agent('service_categories_update', ['category' => $implants->id, 'values' => ['slug' => ['en' => 'dental-implants']], 'dry_run' => true]));

        $this->assertSame(['en' => '/services/dental-implants'], $dry['category']['paths']);
        $this->assertSame('implants', ServiceCategory::query()->findOrFail($implants->id)->getTranslation('slug', 'en'));
    }

    #[Test]
    public function a_category_reorder_refuses_an_id_that_is_not_one(): void
    {
        $implants = $this->category('implants');

        $this->agent('service_categories_reorder', ['ids' => [$implants->id, 99]])->assertHasErrors(['#99']);
    }

    #[Test]
    public function unknown_arguments_and_fields_are_refused(): void
    {
        $crowns = $this->service('crowns');

        $this->agent('services_create', ['title' => 'Bridges', 'lead' => ['en' => 'x']])->assertHasErrors(['services_create has no argument [lead]']);
        $this->agent('services_create', ['title' => 'Bridges', 'values' => ['price' => 10]])->assertHasErrors(['services_create has no field [price]']);
        $this->agent('services_update', ['service' => $crowns->id, 'values' => ['price' => 10]])->assertHasErrors(['services_update has no field [price]']);

        $this->assertSame(1, Service::query()->count());
    }

    #[Test]
    public function a_service_in_the_bin_is_restored_or_purged(): void
    {
        $crowns = $this->service('crowns');
        $bridges = $this->service('bridges');

        $this->agent('services_restore', ['service' => $crowns->id])->assertHasErrors(['not in the bin']);

        $crowns->delete();
        $bridges->delete();

        $this->agent('services_restore', ['service' => $crowns->id, 'dry_run' => true])->assertOk();
        $this->assertTrue($crowns->refresh()->trashed());

        $restored = $this->content($this->agent('services_restore', ['service' => $crowns->id]));

        $this->assertSame('/services/crowns', $restored['service']['urls']['en']['path']);

        $this->agent('services_purge', ['service' => $bridges->id, 'dry_run' => true])->assertOk();
        $this->assertNotNull(Service::withTrashed()->find($bridges->id));

        $this->agent('services_purge', ['service' => $bridges->id])->assertOk();
        $this->assertNull(Service::withTrashed()->find($bridges->id));
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

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
