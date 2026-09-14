<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Settings\Settings;

final class RedirectsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A page the site does have, so that "the middleware answered first" is distinguishable
        // from "there was nothing else to answer".
        Route::middleware('web')->get('/new', fn (): string => 'the new address');
        Route::middleware('web')->get('/old', fn (): string => 'the old address');
        Route::middleware('web')->get('/catalog/{any}', fn (): string => 'catalogue')->where('any', '.*');
    }

    #[Test]
    public function an_address_that_moved_answers_before_the_route_does(): void
    {
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/new']);

        $this->get('/old')->assertRedirect('/new')->assertStatus(301);
    }

    #[Test]
    public function it_catches_an_address_the_site_no_longer_has_a_route_for(): void
    {
        // The case the whole thing exists for, and the one a middleware group cannot serve: a
        // request for an address with no route never enters a group, because the router throws
        // first. Left in `web`, redirects would fire only on pages that still work.
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/gone', 'target' => '/new']);

        $this->get('/gone')->assertRedirect('/new')->assertStatus(301);
    }

    #[Test]
    public function it_counts_what_it_sent(): void
    {
        $redirect = SeoRedirect::query()->create([
            'match_type' => 'exact',
            'pattern' => '/old',
            'target' => '/new',
            'status' => 302,
        ]);

        $this->get('/old')->assertStatus(302);
        $this->get('/old')->assertStatus(302);

        $redirect->refresh();

        $this->assertSame(2, $redirect->hits);
        $this->assertNotNull($redirect->last_hit_at);
    }

    #[Test]
    public function a_mask_puts_back_what_it_matched(): void
    {
        SeoRedirect::query()->create([
            'match_type' => 'mask',
            'pattern' => '/catalog/*',
            'target' => '/shop/$1',
        ]);

        $this->get('/catalog/shoes')->assertRedirect('/shop/shoes');
    }

    #[Test]
    public function a_redirect_to_itself_is_stepped_over(): void
    {
        // Not refused when it is saved: a mask redirect is a loop only for some of the
        // addresses it covers, and refusing it would make the rest unwritable.
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/old/']);

        $this->get('/old')->assertOk()->assertSee('the old address');
    }

    #[Test]
    public function an_inactive_redirect_stops_at_once(): void
    {
        $redirect = SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/new']);

        $this->get('/old')->assertRedirect('/new');

        $redirect->update(['is_active' => false]);

        $this->get('/old')->assertOk();
    }

    #[Test]
    public function it_never_redirects_the_panel_itself(): void
    {
        // A mask an editor wrote for the site must not be able to lock them out of the screen
        // they wrote it on — and out of the one where they could take it back.
        SeoRedirect::query()->create(['match_type' => 'mask', 'pattern' => '/**', 'target' => '/new']);

        $this->get('/cms')->assertOk();
        $this->getJson('/api/cms/manifest')->assertStatus(401);
    }

    #[Test]
    public function robots_txt_is_the_setting_and_nothing_is_a_404(): void
    {
        $this->get('/robots.txt')->assertNotFound();

        app(Settings::class)->save(['seo.robots-txt' => "User-agent: *\nDisallow: /cms"]);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /cms');
    }
}
