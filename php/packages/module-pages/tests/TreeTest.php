<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Exceptions\PagesException;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

class TreeTest extends TestCase
{
    #[Test]
    public function the_migration_leaves_one_root_with_no_address_of_its_own(): void
    {
        $home = $this->home();

        $this->assertTrue($home->isRoot());
        $this->assertSame('', $home->routePath());
        $this->assertFalse($home->isPublished(), 'the site decides when its front page goes live');
        $this->assertSame(1, Page::query()->roots()->count());
    }

    #[Test]
    public function a_child_of_the_home_page_is_not_under_it_in_the_address(): void
    {
        $about = $this->page('about');

        $this->assertSame('about', $about->routePath());
        $this->assertSame('about/mission', $this->page('mission', $about)->routePath());
    }

    #[Test]
    public function moving_a_branch_rewrites_the_addresses_under_it_and_leaves_aliases(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $red = $this->page('red', $shoes);

        $archive = $this->page('archive');
        $shoes->appendTo($archive);

        $this->assertSame('archive/shoes', $shoes->refresh()->routePath());
        $this->assertSame('archive/shoes/red', $red->refresh()->routePath());

        $this->assertSame(Route::CANONICAL, $this->route('archive/shoes/red')?->kind);
        $this->assertSame(Route::ALIAS, $this->route('catalog/shoes/red')?->kind, 'the old address keeps answering');
    }

    #[Test]
    public function the_home_page_cannot_be_moved(): void
    {
        $about = $this->page('about');

        $this->expectException(PagesException::class);

        $this->home()->appendTo($about);
    }

    #[Test]
    public function the_home_page_cannot_be_deleted(): void
    {
        $this->expectException(PagesException::class);

        $this->home()->delete();
    }

    #[Test]
    public function a_page_may_be_dropped_into_the_home_page(): void
    {
        $about = $this->page('about');
        $mission = $this->page('mission', $about);

        $this->assertTrue($mission->appendTo($this->home()));
        $this->assertSame('mission', $mission->refresh()->routePath());
    }

    #[Test]
    public function there_is_never_a_second_root(): void
    {
        $this->expectException(PagesException::class);

        Page::query()->create(['title' => 'Another home', 'slug' => 'another']);
    }

    #[Test]
    public function the_home_page_refuses_an_address(): void
    {
        $home = $this->home();
        $home->slug = 'home';

        $this->expectException(PagesException::class);

        $home->save();
    }

    #[Test]
    public function what_may_be_done_to_a_node_is_answered_per_node(): void
    {
        $this->assertSame(
            ['move' => false, 'delete' => false, 'address' => false],
            $this->home()->capabilities(),
        );

        $this->assertSame(
            ['move' => true, 'delete' => true, 'address' => true],
            $this->page('about')->capabilities(),
        );
    }

    private function route(string $path): ?Route
    {
        return Route::query()->where('locale', 'en')->where('path', $path)->first();
    }
}
