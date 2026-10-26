<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Facades\Preview;

/**
 * The password over a site in testing and the administrator signed in to the panel: the panel's
 * preview of a page, what the preview loads and links to, and «Open on the site» all reach the
 * site with the panel's session and without the pair — a stranger still gets the dialog.
 *
 * Here rather than in `module-admin`, which owns the gate: the frame's own tests have no
 * administrator to sign in, and the question is about one.
 */
final class SiteGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-admin.gate.enabled', true);
        $this->app['config']->set('webx-admin.gate.users', 'client:secret');
    }

    #[Test]
    public function a_stranger_gets_the_dialog_on_the_page_and_on_its_preview(): void
    {
        $page = $this->page('pricing');

        $this->get('/pricing')->assertStatus(401);
        $this->get('/_preview/page/'.$page->getKey())->assertStatus(401);
        $this->withSessionCookie('not-a-session')->get('/pricing')->assertStatus(401);
    }

    #[Test]
    public function an_administrator_signed_in_to_the_panel_passes_without_the_pair(): void
    {
        $page = $this->page('pricing');
        $session = $this->signedIn($this->editor());

        $response = $this->withSessionCookie($session)->get('/pricing')->assertOk();
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertContains('Cookie', array_map('trim', explode(',', implode(',', $response->headers->all('vary')))));

        // The preview, and the preview once its token has run out under an open editor.
        $this->withSessionCookie($session)->get(Preview::url($page))->assertOk();
        $this->withSessionCookie($session)->get('/_preview/page/'.$page->getKey())->assertStatus(403);
        $this->withSessionCookie($session)->get('/no-such-page')->assertNotFound();
    }

    #[Test]
    public function a_switched_off_account_does_not(): void
    {
        $this->page('pricing');
        $editor = $this->editor();
        $session = $this->signedIn($editor);
        $editor->forceFill(['is_active' => false])->save();

        $this->withSessionCookie($session)->get('/pricing')->assertStatus(401);
    }

    /** A session in the store with the panel's guard signed in, as the panel's sign-in leaves it. */
    private function signedIn(CmsUser $admin): string
    {
        $store = clone $this->app['session']->driver();
        $store->setId(null);
        $store->put($this->app['auth']->guard('cms')->getName(), $admin->getKey());
        $store->save();

        return $store->getId();
    }

    private function withSessionCookie(string $id): static
    {
        return $this->withCookie((string) $this->app['config']->get('session.cookie'), $id);
    }
}
