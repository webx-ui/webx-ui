<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Testing\AssertsPermissions;
use WebxUi\CatalogCollections\Panel\CollectionsModule;

/**
 * Nothing of the collections is open to an administrator who holds no permission, every permission
 * asked for is one a module declares, and the section opens to whoever may read or edit the
 * catalogue (WEBX_UI_PERMISSIONS.md §15).
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission('WebxUi\\CatalogCollections\\');
    }

    #[Test]
    public function the_section_needs_no_permission_of_its_own(): void
    {
        $module = $this->app->make(CollectionsModule::class);

        $this->assertTrue($this->app->make(ModuleRegistry::class)->has('catalog-collections'));

        $this->assertSame([], $module->permissions());
        $this->assertSame(['catalog.view', 'catalog.manage'], $module->visibleWith());
    }

    #[Test]
    public function the_menu_opens_it_to_either_permission_of_the_catalogue_and_to_nobody_else(): void
    {
        $this->assertTrue($this->allowed(['catalog.view']));
        $this->assertTrue($this->allowed(['catalog.manage']));
        $this->assertFalse($this->allowed([]));
    }

    #[Test]
    public function a_reader_reads_and_only_an_editor_writes(): void
    {
        $reader = $this->manager(['catalog.view']);

        $this->actingAs($reader, 'cms')->getJson($this->api())->assertOk();
        $this->actingAs($reader, 'cms')->postJson($this->api('preview'), ['source' => 'manual'])->assertOk();
        $this->actingAs($reader, 'cms')->postJson($this->api(), ['title' => 'Picks'])->assertForbidden();
        $this->actingAs($this->manager([]), 'cms')->getJson($this->api())->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function allowed(array $permissions): bool
    {
        $modules = $this->actingAs($this->manager($permissions), 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');

        $this->assertIsArray($modules);

        foreach ($modules as $module) {
            $this->assertIsArray($module);

            if ($module['id'] === 'catalog-collections') {
                return (bool) $module['allowed'];
            }
        }

        return false;
    }
}
