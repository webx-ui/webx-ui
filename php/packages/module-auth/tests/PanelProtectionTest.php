<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\AuthServiceProvider;

/**
 * The reason this package exists before any other module: until it is installed, the panel's
 * API answers anyone.
 */
final class PanelProtectionTest extends TestCase
{
    #[Test]
    public function the_manifest_is_closed_to_a_stranger(): void
    {
        $this->getJson('/api/cms/manifest')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function the_manifest_opens_for_an_administrator(): void
    {
        $this->actingAs($this->admin(), 'cms')
            ->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.modules.0.id', 'users');
    }

    #[Test]
    public function the_shell_stays_public(): void
    {
        // It carries no data and it is what draws the login form, so closing it would leave
        // nowhere to sign in from.
        $this->get('/cms')->assertOk()->assertSee('id="webx-app"', false);
    }

    #[Test]
    public function installing_this_package_is_what_closed_the_panel(): void
    {
        $this->assertSame(
            ['web', 'cms.auth', 'webx.panel-locale'],
            config('webx-admin.api_middleware'),
        );
    }

    /**
     * Driven through the provider rather than through configuration, because Testbench applies
     * `defineEnvironment` after the providers have registered, while a real application loads
     * its config files long before. Setting the flag the usual way would test the harness.
     */
    #[Test]
    public function a_panel_with_its_own_middleware_stack_is_left_alone(): void
    {
        config()->set('webx-auth.protect_panel', false);
        config()->set('webx-admin.api_middleware', ['api', 'my-own-guard']);

        (new AuthServiceProvider($this->app))->register();

        $this->assertSame(['api', 'my-own-guard'], config('webx-admin.api_middleware'));
    }

    #[Test]
    public function otherwise_the_provider_replaces_the_stack(): void
    {
        config()->set('webx-auth.protect_panel', true);
        config()->set('webx-admin.api_middleware', ['api']);

        (new AuthServiceProvider($this->app))->register();

        $this->assertSame(['web', 'cms.auth', 'webx.panel-locale'], config('webx-admin.api_middleware'));
    }
}
