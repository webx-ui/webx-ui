<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Models\CmsUser;

/**
 * The manifest is built for whoever asks (WEBX_UI_PERMISSIONS.md §11): every module is still in
 * it — other sections read each other's `meta` — but `allowed` says which of them this
 * administrator may open, and the menu and the router go by it.
 */
final class ManifestAccessTest extends TestCase
{
    #[Test]
    public function a_section_is_allowed_by_any_of_its_permissions(): void
    {
        $reader = $this->admin('reader@example.test');
        $reader->roles()->attach($this->role('readers', ['cache.view']));

        $allowed = $this->allowed($reader);

        $this->assertTrue($allowed['cache']);
        $this->assertFalse($allowed['admins']);
        // No permission of its own and none it is visible with: everybody's.
        $this->assertTrue($allowed['connect']);
    }

    #[Test]
    public function a_section_without_permissions_of_its_own_is_opened_by_the_ones_it_names(): void
    {
        $this->app->make(ModuleRegistry::class)->register(new class extends AbstractModule implements HasVisibility
        {
            public function id(): string
            {
                return 'dictionary';
            }

            /**
             * @return list<string>
             */
            public function visibleWith(): array
            {
                return ['cache.reset'];
            }
        });

        $reader = $this->admin('reader@example.test');
        $reader->roles()->attach($this->role('readers', ['cache.view']));
        $this->assertFalse($this->allowed($reader)['dictionary']);

        $resetter = $this->admin('resetter@example.test');
        $resetter->roles()->attach($this->role('resetters', ['cache.reset']));
        $this->assertTrue($this->allowed($resetter)['dictionary']);
    }

    #[Test]
    public function a_super_administrator_opens_everything_and_nobody_reads_anybody_elses(): void
    {
        $super = $this->admin('super@example.test', super: true);
        $this->assertNotContains(false, $this->allowed($super));

        // The words are cached per language; who may open what is not in them.
        $nobody = $this->admin('nobody@example.test');
        $this->assertFalse($this->allowed($nobody)['cache']);
        $this->assertTrue($this->allowed($super)['cache']);
    }

    /**
     * @return array<string, bool>
     */
    private function allowed(CmsUser $as): array
    {
        $modules = $this->actingAs($as, 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');

        $this->assertIsArray($modules);
        $allowed = [];

        foreach ($modules as $module) {
            $this->assertIsArray($module);
            $allowed[(string) $module['id']] = (bool) $module['allowed'];
        }

        return $allowed;
    }
}
