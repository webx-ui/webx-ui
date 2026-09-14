<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Admin\Tests\Fixtures\PagesModule;

final class ScreenEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Screens::register('settings.index', __DIR__.'/Fixtures/screens/settings.json');
        Screens::extend('settings.index', [['op' => 'set', 'target' => 'map', 'label' => 'On the map']]);
    }

    #[Test]
    public function a_screen_arrives_patched_translated_and_cut_down_to_the_permissions(): void
    {
        $this->actingAs(new Editor(['settings.view']))
            ->getJson('/api/cms/screens/settings.index')
            ->assertOk()
            ->assertJsonPath('data.screen', 'settings.index')
            ->assertJsonPath('data.root.0.children.0.label', 'System')
            ->assertJsonPath('data.root.0.children.0.children.0.children.0.label', 'Sections')
            ->assertJsonPath('data.root.0.children.0.children.0.children.1.id', 'map')
            ->assertJsonPath('data.root.0.children.0.children.0.children.1.label', 'On the map')
            ->assertJsonCount(2, 'data.root.0.children.0.children.0.children');
    }

    #[Test]
    public function the_administrator_with_the_permission_sees_the_node(): void
    {
        $this->actingAs(new Editor(['settings.manage']))
            ->getJson('/api/cms/screens/settings.index')
            ->assertOk()
            ->assertJsonPath('data.root.0.children.0.children.0.children.1.id', 'secret')
            ->assertJsonCount(3, 'data.root.0.children.0.children.0.children');
    }

    #[Test]
    public function the_screen_speaks_the_panel_language(): void
    {
        $this->actingAs(new Editor)
            ->getJson('/api/cms/screens/settings.index', ['X-Webx-Locale' => 'ru'])
            ->assertOk()
            ->assertJsonPath('data.root.0.children.0.label', 'Система');
    }

    #[Test]
    public function an_unknown_screen_is_a_404_with_words(): void
    {
        $this->getJson('/api/cms/screens/settings.nope')
            ->assertNotFound()
            ->assertJsonPath('message', 'There is no screen named settings.nope.');
    }

    #[Test]
    public function the_manifest_lists_screen_names_groups_and_each_module_group(): void
    {
        $this->register(new PagesModule);

        $this->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.screens', ['settings.index'])
            ->assertJsonPath('data.groups.0.id', 'system')
            ->assertJsonPath('data.groups.0.title', 'System')
            ->assertJsonPath('data.groups.0.order', 900)
            ->assertJsonPath('data.modules.0.group', null);
    }
}
