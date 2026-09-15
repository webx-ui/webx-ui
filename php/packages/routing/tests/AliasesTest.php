<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Aliases\Alias;
use WebxUi\Routing\Aliases\RouteAliases;
use WebxUi\Routing\Resolver;

/**
 * What a panel is allowed to see of the registry (§12.2).
 *
 * A page of rows and a filter, and nothing that writes: the trail of a rename belongs to the
 * entity that moved. `module-seo` shows these beside the redirects an editor wrote by hand, and
 * this contract is the whole of what passes between the two packages.
 */
final class AliasesTest extends TestCase
{
    #[Test]
    public function a_rename_shows_up_with_the_address_it_now_leads_to(): void
    {
        $page = $this->page('about');

        $page->update(['slug' => 'about-us']);

        $aliases = $this->aliases()->search();

        $this->assertSame(1, $aliases->total());

        /** @var Alias $alias */
        $alias = $aliases->items()[0];

        $this->assertSame('about', $alias->path);
        $this->assertSame('about-us', $alias->target);
        $this->assertSame('page', $alias->entityType);
        $this->assertSame($page->getKey(), $alias->entityId);
        $this->assertStringEndsWith('/about-us', (string) $alias->targetUrl);
    }

    #[Test]
    public function a_branch_that_moved_leaves_one_row_for_every_page_under_it(): void
    {
        $about = $this->page('about');
        $this->page('mission', $about);
        $this->page('team', $about);

        $about->update(['slug' => 'company']);

        // Three addresses died at once, which is the reason this list is paginated and searched
        // rather than simply printed: one click on a tree moves a thousand of them.
        $this->assertSame(
            ['about', 'about/mission', 'about/team'],
            collect($this->aliases()->search(perPage: 10)->items())->pluck('path')->sort()->values()->all(),
        );

        $this->assertSame(2, $this->aliases()->search(perPage: 2)->lastPage());
    }

    #[Test]
    public function the_search_matches_the_address_and_where_it_leads(): void
    {
        $this->page('about')->update(['slug' => 'about-us']);
        $this->page('parts')->update(['slug' => 'catalogue']);

        $this->assertSame('about', $this->aliases()->search('ABOUT')->items()[0]->path);
        $this->assertSame('parts', $this->aliases()->search('catalogue')->items()[0]->path);
        $this->assertSame(0, $this->aliases()->search('nothing-like-it')->total());
    }

    #[Test]
    public function an_address_is_looked_up_in_the_language_of_the_site_not_of_the_panel(): void
    {
        $this->useLocales(['en', 'uk']);

        $page = $this->page('about');

        // What the panel does before it lets an editor redirect an address away: an
        // administrator working in another language is asking about an address, not about the
        // half of the site that is in their own language.
        app()->setLocale('uk');

        $resolution = app(Resolver::class)->lookup('/About/');

        $this->assertNotNull($resolution);
        $this->assertSame($page->getKey(), $resolution->route->entity_id);
        $this->assertSame('en', $resolution->route->locale);

        $this->assertNull(app(Resolver::class)->lookup('/nothing-here'));
    }

    private function aliases(): RouteAliases
    {
        return app(RouteAliases::class);
    }
}
