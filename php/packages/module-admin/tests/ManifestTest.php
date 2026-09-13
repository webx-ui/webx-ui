<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Tests\Fixtures\MediaModule;
use WebxUi\Admin\Tests\Fixtures\PagesModule;

final class ManifestTest extends TestCase
{
    #[Test]
    public function the_manifest_describes_the_panel_and_its_modules(): void
    {
        $this->register(new PagesModule, new MediaModule);

        $this->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.title', 'WebX UI')
            ->assertJsonPath('data.path', '/cms')
            ->assertJsonPath('data.apiPath', '/api/cms')
            ->assertJsonPath('data.modules.0.id', 'media-library')
            ->assertJsonPath('data.modules.1.id', 'pages')
            ->assertJsonPath('data.modules.1.title', 'Pages')
            ->assertJsonPath('data.modules.1.icon', 'file-text')
            ->assertJsonPath('data.modules.1.order', 10)
            ->assertJsonPath('data.modules.1.permissions', ['pages.view', 'pages.manage'])
            ->assertJsonPath('data.modules.1.meta.tree', true);
    }

    #[Test]
    public function a_panel_without_modules_still_answers(): void
    {
        $this->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.modules', []);
    }

    #[Test]
    public function module_data_cannot_shadow_the_fields_around_it(): void
    {
        $this->register(new class extends AbstractModule
        {
            public function id(): string
            {
                return 'sneaky';
            }

            /**
             * @return array<string, mixed>
             */
            public function manifest(): array
            {
                return ['id' => 'something-else', 'permissions' => ['*']];
            }
        });

        $this->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.modules.0.id', 'sneaky')
            ->assertJsonPath('data.modules.0.permissions', [])
            ->assertJsonPath('data.modules.0.meta.id', 'something-else');
    }

    #[Test]
    public function the_paths_follow_the_configuration(): void
    {
        config()->set('webx-admin.title', 'Acme');
        config()->set('webx-admin.path', '/panel');
        config()->set('webx-admin.api_path', 'api/panel');

        // Routes were registered from the defaults at boot, so this checks the payload rather
        // than a new address — moving the panel means clearing the route cache anyway.
        $this->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.title', 'Acme')
            ->assertJsonPath('data.path', '/panel')
            ->assertJsonPath('data.apiPath', '/api/panel');
    }
}
