<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Routing\Models\Route;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * The catalogue by its other doors (§4.8): the same list, the same screen, the same order code.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function both_sections_offer_their_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['services_list', 'services_get', 'services_create', 'services_update', 'services_publish', 'services_unpublish', 'services_delete', 'services_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('services')),
        );

        $this->assertSame(
            ['service_categories_list', 'service_categories_create', 'service_categories_update', 'service_categories_delete', 'service_categories_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('service-categories')),
        );

        $this->assertSame(['services.view', 'services.manage'], $registry->tool('services_list')->permissions());
        $this->assertSame(['services.manage'], $registry->tool('services_reorder')->permissions());
        $this->assertSame(['services.categories.manage'], $registry->tool('service_categories_create')->permissions());

        $this->assertContains('services://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['services.view']);

        $this->agent('services_list', [], $reader)->assertOk();
        $this->agent('services_create', ['title' => 'Nope'], $reader)->assertHasErrors(['[services.manage]']);
    }

    #[Test]
    public function the_list_narrowed_to_a_category_is_in_that_categorys_order(): void
    {
        $implants = $this->category('implants');
        $crowns = $this->service('crowns');
        $bridges = $this->service('bridges');

        foreach ([$crowns, $bridges] as $service) {
            $service->syncCategories([(int) $implants->getKey()]);
        }

        $this->agent('services_reorder', ['services' => ['/services/bridges', $crowns->getKey()], 'category' => 'implants'])->assertOk();

        $content = $this->content($this->agent('services_list', ['category' => 'implants']));
        $this->assertSame([$bridges->getKey(), $crowns->getKey()], array_column($content['services'], 'id'));

        // The whole list has not moved.
        $content = $this->content($this->agent('services_list'));
        $this->assertSame([$crowns->getKey(), $bridges->getKey()], array_column($content['services'], 'id'));
    }

    #[Test]
    public function reordering_a_category_refuses_a_service_it_does_not_hold(): void
    {
        $this->category('implants');
        $crowns = $this->service('crowns');

        $this->agent('services_reorder', ['services' => [$crowns->getKey()], 'category' => 'implants'])
            ->assertHasErrors(['Not in this category']);
    }

    #[Test]
    public function create_update_and_publish_go_through_the_draft(): void
    {
        $category = $this->category('implants');

        $created = $this->content($this->agent('services_create', [
            'title' => 'Dental implants',
            'values' => ['categories' => [$category->getKey()]],
        ]));

        $this->assertSame('/services/dental-implants', $created['service']['urls']['en']['path']);
        $this->assertSame(Service::STATUS_DRAFT, $created['service']['status']);
        $this->assertSame((int) $category->getKey(), $created['service']['categories'][0]['id']);

        $id = $created['service']['id'];

        $this->agent('services_update', [
            'service' => $id,
            'values' => ['lead' => ['en' => 'Turnkey.']],
            'revision' => $created['revision'],
        ])->assertOk();

        $this->agent('services_update', ['service' => $id, 'values' => ['lead' => 'Again'], 'revision' => $created['revision']])
            ->assertHasErrors(['changed since you read it']);

        $published = $this->content($this->agent('services_publish', ['service' => '/services/dental-implants']));

        $this->assertSame(Service::STATUS_PUBLISHED, $published['service']['status']);
        $this->assertSame('Turnkey.', Service::query()->findOrFail($id)->getTranslation('lead', 'en'));
    }

    #[Test]
    public function an_address_another_one_holds_is_refused_with_its_name(): void
    {
        $this->category('implants');

        $this->agent('services_create', ['title' => 'Implants'])->assertHasErrors(['already taken']);
    }

    #[Test]
    public function a_refused_value_leaves_no_service_and_no_address_behind(): void
    {
        // A translated field given as a bare string: the screen refuses it after the row is in.
        $this->agent('services_create', ['title' => 'Dental implants', 'values' => ['lead' => 'Turnkey.']])
            ->assertHasErrors(['lead']);

        $this->assertSame(0, Service::query()->withTrashed()->count());
        $this->assertSame(0, Route::query()->count());

        // And the address is still free for the call that gets it right.
        $this->agent('services_create', ['title' => 'Dental implants', 'values' => ['lead' => ['en' => 'Turnkey.']]])
            ->assertOk();
    }

    #[Test]
    public function the_body_is_not_written_here(): void
    {
        $service = $this->service('crowns');

        $this->agent('services_update', ['service' => $service->getKey(), 'values' => ['blocks' => []]])
            ->assertHasErrors(['blocks_edit_content']);
    }

    #[Test]
    public function a_field_of_the_project_is_written_and_refused_as_in_the_panel(): void
    {
        Screens::extend(Service::SCREEN, [[
            'op' => 'add',
            'target' => 'project-fields',
            'node' => ['id' => 'price-from', 'type' => 'wx-input-number', 'name' => 'price-from', 'label' => 'Price from'],
        ]]);

        $service = $this->service('crowns');

        $this->agent('services_update', ['service' => $service->getKey(), 'values' => ['price-from' => 'a lot']])
            ->assertHasErrors(['price-from']);

        $content = $this->content($this->agent('services_update', ['service' => $service->getKey(), 'values' => ['price-from' => 450]]));

        $this->assertSame(450, $content['values']['price-from']);

        $this->agent('services_publish', ['service' => $service->getKey()])->assertOk();

        $this->assertSame(450, $service->refresh()->extra('price-from'));
    }

    #[Test]
    public function the_catalogue_lists_every_category_with_its_services_in_its_order_and_drafts_too(): void
    {
        $implants = $this->category('implants');
        $hidden = $this->category('archive', visible: false);
        $crowns = $this->service('crowns');
        $draft = $this->service('bridges', published: false);
        $loose = $this->service('whitening');

        $crowns->syncCategories([(int) $implants->getKey()]);
        $draft->syncCategories([(int) $implants->getKey(), (int) $hidden->getKey()]);

        $catalog = ($this->resource('services://catalog')->handler)();

        $this->assertSame(['implants', 'archive'], array_column($catalog['categories'], 'slug'));
        $this->assertFalse($catalog['categories'][1]['visible']);
        $this->assertSame([$crowns->getKey(), $draft->getKey()], array_column($catalog['categories'][0]['services'], 'id'));
        $this->assertSame(Service::STATUS_DRAFT, $catalog['categories'][0]['services'][1]['status']);
        $this->assertSame([$loose->getKey()], array_column($catalog['uncategorised'], 'id'));
        $this->assertSame($crowns->url(), $catalog['categories'][0]['services'][0]['url']);
    }

    #[Test]
    public function a_category_in_use_is_not_deleted_by_an_agent_either(): void
    {
        $implants = $this->category('implants');
        $this->service('crowns')->syncCategories([(int) $implants->getKey()]);

        $this->agent('service_categories_delete', ['category' => 'implants'])->assertHasErrors();

        $this->assertNotNull(ServiceCategory::query()->find($implants->getKey()));
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
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
