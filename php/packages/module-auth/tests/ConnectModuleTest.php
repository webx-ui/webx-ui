<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;

/**
 * The page that tells a person how to connect an agent exists only where there is a door to
 * connect to — and the address it prints is absolute, because it is copied into a program on
 * another machine.
 */
final class ConnectModuleTest extends TestCase
{
    #[Test]
    public function the_section_carries_the_address_agents_connect_to(): void
    {
        $module = $this->connect();

        $this->assertNotNull($module);
        $this->assertSame('system', $module->group());
        $this->assertSame([], $module->permissions());
        $this->assertSame(['url' => 'http://localhost/api/cms/mcp'], $module->manifest());
    }

    #[Test]
    public function a_configured_path_is_the_one_that_is_printed(): void
    {
        config()->set('webx-mcp.path', 'agents/mcp');

        $this->assertSame(['url' => 'http://localhost/agents/mcp'], $this->connect()?->manifest());
    }

    #[Test]
    public function a_panel_with_the_door_shut_has_no_section(): void
    {
        $this->assertNull($this->connect());
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The one test that expects no section says so by name, the way a site switches the
        // HTTP server off.
        if ($this->name() === 'a_panel_with_the_door_shut_has_no_section') {
            $app['config']->set('webx-mcp.path', false);
        }
    }

    private function connect(): ?Module
    {
        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            if ($module->id() === 'connect') {
                return $module;
            }
        }

        return null;
    }
}
