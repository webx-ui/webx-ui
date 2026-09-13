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

    #[Test]
    public function it_loads_the_panel_assets_it_is_given(): void
    {
        config()->set('webx-admin.assets', ['/webx/webx.css', '/webx/webx.js']);

        $this->get('/cms')
            ->assertOk()
            ->assertSee('<link rel="stylesheet" href="/webx/webx.css">', false)
            ->assertSee('<script type="module" src="/webx/webx.js" defer></script>', false);
    }

    #[Test]
    public function a_query_string_does_not_confuse_a_stylesheet_for_a_script(): void
    {
        config()->set('webx-admin.assets', ['/webx/webx.css?v=3']);

        $this->get('/cms')
            ->assertOk()
            ->assertSee('<link rel="stylesheet" href="/webx/webx.css?v=3">', false)
            ->assertDontSee('<script type="module" src="/webx/webx.css', false);
    }

    #[Test]
    public function with_no_assets_the_page_is_deliberately_empty(): void
    {
        // The frame installed and the panel not is a real state, and it should look like one
        // rather than like a broken page.
        $this->get('/cms')
            ->assertOk()
            ->assertSee('id="webx-app"', false)
            ->assertDontSee('<script type="module"', false);
    }
}
