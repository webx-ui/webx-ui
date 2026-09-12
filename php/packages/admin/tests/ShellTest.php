<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;

final class ShellTest extends TestCase
{
    #[Test]
    public function the_panel_root_serves_the_shell(): void
    {
        $this->get('/cms')
            ->assertOk()
            ->assertSee('id="webx-app"', false)
            ->assertSee('content="/api/cms/manifest"', false);
    }

    #[Test]
    public function a_deep_link_serves_the_shell_too(): void
    {
        // The whole point of the catch-all: the front end owns routing below the prefix, and
        // a reloaded page three levels down must not 404.
        $this->get('/cms/pages/42/edit')
            ->assertOk()
            ->assertSee('id="webx-app"', false);
    }

    #[Test]
    public function the_shell_is_kept_out_of_search_results(): void
    {
        $this->get('/cms')->assertSee('noindex', false);
    }

    #[Test]
    public function addresses_outside_the_prefix_are_still_missing(): void
    {
        $this->get('/not-the-panel')->assertNotFound();
    }
}
