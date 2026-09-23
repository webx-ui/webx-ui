<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Exceptions\PathRejected;

/**
 * A blog with no prefix at all (§2.2, §2.11).
 *
 * A class of its own rather than a few tests with the config changed, because the prefix is
 * read once: the route type's formatter and the feed route are both worked out while the
 * providers boot, so changing the value after that changes nothing. That is deliberate — a
 * formatter has to be a pure function of the entity — and it means the setting can only be
 * tested by booting an application that has it.
 */
final class NoPrefixTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-blog.prefix', '');
    }

    #[Test]
    public function articles_and_rubrics_sit_at_the_root_and_tags_one_below(): void
    {
        $article = $this->article('how-to-choose');
        $rubric = $this->rubric('repairs');
        $tag = $this->tag('belts');

        $this->assertSame('how-to-choose', $article->routeCanonical()?->path);
        $this->assertSame('repairs', $rubric->routeCanonical()?->path);
        $this->assertSame('tag/belts', $tag->routeCanonical()?->path);

        $this->get('/how-to-choose')->assertOk();
        $this->get('/tag/belts')->assertOk();
    }

    #[Test]
    public function the_rss_route_closes_its_own_address_to_an_article(): void
    {
        // `Reserved` asks the router, so the module keeps no list of its own: the RSS is a real
        // route, and with no prefix an article slugged `rss` wants exactly that address. The
        // editor is told while they can still change it, rather than left with an article that
        // exists, has an address, and answers with a feed (§10 of the routing spec).
        $this->expectException(PathRejected::class);

        $this->article('rss');
    }

    #[Test]
    public function the_feed_is_left_to_the_site_and_the_rss_is_not(): void
    {
        $routes = $this->app->make('router')->getRoutes();

        // `/` belongs to the site, and a list of articles on it is a page the site writes.
        $this->assertNull($routes->getByName('webx.blog.feed'));

        // The RSS still gets an address: without one there is nothing to subscribe to, and
        // there is nowhere else to put it.
        $this->assertNotNull($routes->getByName('webx.blog.rss'));
        $this->get('/rss')->assertOk();
    }
}
