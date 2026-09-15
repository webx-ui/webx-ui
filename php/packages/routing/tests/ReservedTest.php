<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use Illuminate\Support\Facades\Route as Router;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Reserved;
use WebxUi\Routing\Tests\Fixtures\Category;

/**
 * Addresses the application answers itself (§10).
 *
 * All of these fail at save time rather than at request time, and that is the whole decision:
 * losing silently to a live route leaves an editor with a page that exists everywhere except on
 * the site. The error lands on the slug field, where they can do something about it.
 */
class ReservedTest extends TestCase
{
    #[Test]
    public function an_address_the_project_routes_is_refused(): void
    {
        Router::get('search', fn (): string => 'the project answers');

        $this->assertRefused(fn () => $this->page('search'));
    }

    #[Test]
    public function a_configured_name_is_refused(): void
    {
        $this->app['config']->set('webx-routing.reserved', ['storage']);

        $this->assertRefused(fn () => $this->page('storage'));
    }

    #[Test]
    public function a_reserved_name_closes_the_branch_under_it(): void
    {
        // `storage` is a directory the web server answers from, so `storage/exports` is not a
        // free address either — no route describes it and nothing would ever reach the page.
        $this->app['config']->set('webx-routing.reserved', ['storage']);

        $reserved = $this->app->make(Reserved::class);

        $this->assertTrue($reserved->taken('storage/exports'));
        $this->assertFalse($reserved->taken('storage-room'));
    }

    #[Test]
    public function the_panel_keeps_its_own_prefix(): void
    {
        // Inherited from `module-admin` at runtime rather than copied into the config file, so
        // that a site which moves its panel does not have to remember this second place.
        $this->app['config']->set('webx-admin.path', 'cms');
        $this->app['config']->set('webx-admin.api_path', 'api/cms');

        $reserved = $this->app->make(Reserved::class);

        $this->assertTrue($reserved->taken('cms'));
        $this->assertTrue($reserved->taken('api/cms'));
        $this->assertRefused(fn () => $this->page('cms'));
    }

    #[Test]
    public function the_fallback_route_itself_is_not_a_reason_to_refuse(): void
    {
        // It matches every address there is. If it counted, nothing could ever be saved.
        $page = $this->page('about');

        $this->assertSame('about', $page->routeCanonical()?->path);
    }

    #[Test]
    public function a_type_that_suffixes_walks_past_a_reserved_name(): void
    {
        $this->app['config']->set('webx-routing.reserved', ['parts']);

        // An import must not stop because a supplier's category is called what the application
        // already calls something of its own.
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);

        $this->assertSame('parts-2', $category->refresh()->slug);
        $this->assertSame('parts-2', Route::query()->forEntity($category)->canonical()->value('path'));
    }

    private function assertRefused(callable $save): void
    {
        try {
            $save();
        } catch (PathRejected $rejected) {
            $this->assertArrayHasKey('slug', $rejected->errors());

            return;
        }

        $this->fail('The address was accepted, and the application answers it itself.');
    }
}
