<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Gate\KeepOutOfSearch;
use WebxUi\Admin\Setup\Catalogue;

/**
 * A site with no public part (`webx-admin.public` off) keeps out of search engines: every
 * response says so, a missing page included, and `/robots.txt` disallows everything whoever
 * else would have answered it.
 */
final class KeepOutOfSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/about', static fn (): string => 'About us');
        // What the SEO module answers on a public site; a private one must not get it.
        Route::get('/robots.txt', static fn (): string => "User-agent: *\nAllow: /\n");
    }

    #[Test]
    public function a_public_site_is_left_as_it_is(): void
    {
        $kernel = $this->app->make(HttpKernel::class);
        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertTrue($kernel->hasMiddleware(KeepOutOfSearch::class));

        $this->get('/about')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /');
    }

    #[Test]
    public function a_site_with_no_public_part_is_out_of_search_everywhere(): void
    {
        $this->app['config']->set('webx-admin.public', false);

        $this->get('/about')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/no-such-page')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $robots = $this->get('/robots.txt')->assertOk();
        $this->assertSame("User-agent: *\nDisallow: /\n", $robots->getContent());
        $this->assertStringStartsWith('text/plain', (string) $robots->headers->get('Content-Type'));
    }

    #[Test]
    public function the_panel_and_its_settings_and_the_task_queue_show_a_visitor_nothing(): void
    {
        $catalogue = $this->app->make(Catalogue::class);

        $this->assertFalse($catalogue->hasPublicPart(['admins', 'settings', 'tasks']));
        $this->assertTrue($catalogue->hasPublicPart(['admins', 'settings', 'tasks', 'blog']));
        // Somebody else's module is assumed to show something.
        $this->assertTrue($catalogue->hasPublicPart(['admins', 'acme-shop']));
    }
}
