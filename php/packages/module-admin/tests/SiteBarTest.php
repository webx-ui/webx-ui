<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\SiteBar\EntityLink;
use WebxUi\Admin\SiteBar\Loader;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Admin\Support\Tokens;

/**
 * The frame's half of the panel on the site (WEBX_UI_SITE_BAR.md): its script, its screen and
 * the register. Who gets the bar on which page is tested where there are administrators and
 * addresses — `module-pages/tests/SiteBarTest.php`.
 */
final class SiteBarTest extends TestCase
{
    #[Test]
    public function the_script_is_served_under_its_hash_for_good(): void
    {
        $loader = $this->app->make(Loader::class);

        $this->get('/cms/_bar/loader.'.$loader->hash().'.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    }

    #[Test]
    public function an_old_hash_still_gets_a_working_script_only_not_one_to_keep(): void
    {
        $response = $this->get('/cms/_bar/loader.0123456789ab.js')->assertOk();

        $this->assertSame($this->app->make(Loader::class)->body(), $response->getContent());
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function the_tokens_go_in_for_the_shadow_host_and_not_for_the_page(): void
    {
        $body = $this->app->make(Loader::class)->body();

        $this->assertStringNotContainsString("const TOKENS = ''", $body);
        $this->assertStringContainsString(':host {', $body);
        $this->assertStringContainsString(":host([data-theme='dark'])", $body);
        $this->assertStringContainsString("@media (prefers-color-scheme: dark) {\\n  :host(:not([data-theme='light']))", $body);
        $this->assertStringNotContainsString(':root', $body);
        $this->assertStringContainsString('--wx-bg-surface', Loader::hostTokens(Tokens::css()));
    }

    #[Test]
    public function the_screen_is_the_shell_framed_by_the_site_only(): void
    {
        $this->get('/cms/_bar?url=/pricing')
            ->assertOk()
            ->assertSee('id="webx-app"', false)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    #[Test]
    public function a_site_without_anybody_to_sign_in_never_gets_the_bar(): void
    {
        // No `module-auth` here: no guard to ask, so nobody is an administrator of the page.
        Route::middleware('web')->get('/about', static fn (): string => '<html><body>About</body></html>');

        $response = $this->get('/about')->assertOk();

        $this->assertSame('<html><body>About</body></html>', $response->getContent());
    }

    #[Test]
    public function the_page_tab_is_the_frames_own_and_asks_the_records_permission(): void
    {
        $bar = $this->app->make(SiteBar::class);
        $bar->entity('thing', new EntityLink(
            edit: static fn (Model $record): string => '/things/'.$record->getKey(),
            permission: 'things.manage',
            kind: 'webx-admin::site-bar.kind-page',
        ));

        $thing = new class extends Model {};
        $thing->forceFill(['id' => 7, 'title' => 'Seven']);

        $this->assertSame(['page'], $bar->sections($this->user(['things.manage']), $thing, 'thing'));
        $this->assertSame([], $bar->sections($this->user([]), $thing, 'thing'));
        $this->assertSame([], $bar->sections($this->user(['things.manage']), null, null));
        $this->assertSame([], $bar->sections($this->user(['things.manage']), $thing, 'unregistered'));
    }

    /** @param  list<string>  $permissions */
    private function user(array $permissions): HasPermissions
    {
        return new readonly class($permissions) implements HasPermissions
        {
            /** @param  list<string>  $permissions */
            public function __construct(private array $permissions) {}

            public function hasPermission(string $permission): bool
            {
                return in_array($permission, $this->permissions, true);
            }
        };
    }
}
