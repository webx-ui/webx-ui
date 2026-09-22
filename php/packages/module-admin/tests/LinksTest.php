<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\SiteRoutes;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Admin\Tests\Fixtures\FakeLinkSource;

/**
 * The register of what can be linked to, and the four addresses the picker reads (§13 of the menu
 * spec).
 */
final class LinksTest extends TestCase
{
    #[Test]
    public function sources_are_read_in_the_order_they_asked_for(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'tag', order: 220));
        $this->sources()->register(new FakeLinkSource(type: 'page', order: 100));

        $this->assertSame(['page', 'tag'], array_map(
            static fn ($source): string => $source->type(),
            $this->sources()->all(),
        ));
    }

    #[Test]
    public function a_source_is_found_by_its_morph_alias(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page'));

        $this->assertSame('page', $this->sources()->find('page')?->type());
        $this->assertNull($this->sources()->find('product'));
    }

    #[Test]
    public function a_section_behind_a_permission_is_offered_to_whoever_has_it(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page', permission: 'pages.view'));
        $this->sources()->register(new FakeLinkSource(type: 'landing', permission: null, order: 300));

        $this->assertSame(['landing'], $this->types($this->sources()->allowed(new Editor)));
        $this->assertSame(['page', 'landing'], $this->types($this->sources()->allowed(new Editor(['pages.view']))));
        // Nobody signed in still sees what nothing guards, which is what the picker in a panel
        // with no auth module installed at all is looking at.
        $this->assertSame(['landing'], $this->types($this->sources()->allowed(null)));
    }

    #[Test]
    public function the_endpoint_lists_only_the_sections_this_reader_may_open(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page', permission: 'pages.view', title: 'Pages'));
        $this->sources()->register(new FakeLinkSource(type: 'article', permission: 'blog.articles.view', order: 200));

        $this->actingAs(new Editor(['pages.view']))
            ->getJson('/api/cms/links/sources')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'page')
            ->assertJsonPath('data.0.title', 'Pages')
            ->assertJsonPath('data.0.icon', 'file');
    }

    #[Test]
    public function search_answers_with_the_candidates_of_one_type(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page', candidates: [
            new LinkCandidate(1, 'About us', '/about', true, 'Home'),
            new LinkCandidate(2, 'Pricing', null, false),
        ]));

        $this->actingAs(new Editor)
            ->getJson('/api/cms/links/search?type=page&q=about')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 1)
            ->assertJsonPath('data.0.title', 'About us')
            ->assertJsonPath('data.0.url', '/about')
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.0.hint', 'Home');
    }

    /** An unfinished draft belongs in the picker, marked — a menu is built before it is published. */
    #[Test]
    public function a_candidate_with_no_address_is_offered_and_says_so(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page', candidates: [
            new LinkCandidate(2, 'Pricing', null, false),
        ]));

        $this->actingAs(new Editor)
            ->getJson('/api/cms/links/search?type=page')
            ->assertOk()
            ->assertJsonPath('data.0.url', null)
            ->assertJsonPath('data.0.available', false);
    }

    #[Test]
    public function searching_a_section_this_reader_may_not_open_is_not_found(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page', permission: 'pages.view'));

        $this->actingAs(new Editor)
            ->getJson('/api/cms/links/search?type=page')
            ->assertNotFound();

        $this->actingAs(new Editor)
            ->getJson('/api/cms/links/search?type=nothing-like-it')
            ->assertNotFound();
    }

    #[Test]
    public function resolve_answers_several_types_at_once(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page', candidates: [
            new LinkCandidate(1, 'About us', '/about', true),
        ]));
        $this->sources()->register(new FakeLinkSource(type: 'article', order: 200, candidates: [
            new LinkCandidate(7, 'How we work', '/blog/how-we-work', true),
        ]));

        $response = $this->actingAs(new Editor)
            ->postJson('/api/cms/links/resolve', ['links' => [
                ['type' => 'page', 'id' => 1],
                ['type' => 'article', 'id' => 7],
            ]])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame(
            [['page', 1], ['article', 7]],
            array_map(
                static fn (array $row): array => [$row['type'], $row['id']],
                $response->json('data'),
            ),
        );
    }

    /** A link whose entity has been deleted comes back missing, not as an error. */
    #[Test]
    public function resolving_an_id_that_is_gone_leaves_it_out(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page', candidates: [
            new LinkCandidate(1, 'About us', '/about', true),
        ]));

        $this->actingAs(new Editor)
            ->postJson('/api/cms/links/resolve', ['links' => [
                ['type' => 'page', 'id' => 1],
                ['type' => 'page', 'id' => 999],
            ]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 1);
    }

    #[Test]
    public function resolving_a_type_this_reader_may_not_open_leaves_it_out_rather_than_refusing(): void
    {
        $this->sources()->register(new FakeLinkSource(type: 'page', candidates: [
            new LinkCandidate(1, 'About us', '/about', true),
        ]));
        $this->sources()->register(new FakeLinkSource(type: 'article', order: 200, permission: 'blog.articles.view', candidates: [
            new LinkCandidate(7, 'How we work', '/blog/how-we-work', true),
        ]));

        $this->actingAs(new Editor)
            ->postJson('/api/cms/links/resolve', ['links' => [
                ['type' => 'page', 'id' => 1],
                ['type' => 'article', 'id' => 7],
            ]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'page');
    }

    #[Test]
    public function the_routes_endpoint_offers_the_named_pages_of_the_site(): void
    {
        Route::get('account', static fn (): string => 'ok')->name('account');
        Route::get('orders/{order}', static fn (): string => 'ok')->name('orders.show');
        Route::post('subscribe', static fn (): string => 'ok')->name('subscribe');
        Route::get('unnamed', static fn (): string => 'ok');

        $paths = $this->actingAs(new Editor)
            ->getJson('/api/cms/links/routes')
            ->assertOk()
            ->json('data');

        $paths = array_column($paths, 'path');

        $this->assertContains('/account', $paths);
        // A route with a parameter has no one address; a POST is not a page; an unnamed route is
        // usually a callback. None of the three belongs in a list somebody picks a link from.
        $this->assertNotContains('/orders/{order}', $paths);
        $this->assertNotContains('/subscribe', $paths);
        $this->assertNotContains('/unnamed', $paths);
    }

    /** A link into the CMS is not a link on the site. */
    #[Test]
    public function the_panels_own_addresses_are_not_offered(): void
    {
        Route::get('account', static fn (): string => 'ok')->name('account');

        // Both prefixes are registered as ordinary named GET routes by this package — the
        // locales endpoint, the dictionary — so an empty answer here would prove nothing.
        $paths = array_column($this->app->make(SiteRoutes::class)->all(), 'path');

        $this->assertSame(['/account'], $paths);
    }

    /**
     * Found on the demo site: the first eight entries were the OAuth dance and a runtime script,
     * in a list whose whole job is to save somebody from remembering `/account`.
     */
    #[Test]
    public function the_machinery_of_installed_packages_is_not_offered(): void
    {
        Route::get('account', static fn (): string => 'ok')->name('account');
        Route::get('oauth/authorize', static fn (): string => 'ok')->name('passport.authorize');
        Route::get('.well-known/oauth-protected-resource', static fn (): string => 'ok')->name('mcp.resource');
        Route::get('blocks/runtime.js', static fn (): string => 'ok')->name('webx.blocks.runtime');

        $this->assertSame(['/account'], array_column($this->app->make(SiteRoutes::class)->all(), 'path'));
    }

    /** A site names its own machinery without waiting for us to name it. */
    #[Test]
    public function a_site_may_add_masks_of_its_own(): void
    {
        Route::get('internal/health', static fn (): string => 'ok')->name('health');
        Route::get('account', static fn (): string => 'ok')->name('account');

        $this->app['config']->set('webx-admin.links.exclude', [...SiteRoutes::EXCLUDE, 'internal/*']);

        $this->assertSame(['/account'], array_column($this->app->make(SiteRoutes::class)->all(), 'path'));
    }

    #[Test]
    public function a_link_is_read_out_of_whatever_arrived(): void
    {
        $link = Link::fromArray([
            'target' => 'entity',
            'entity_type' => 'page',
            'entity_id' => '12',
            // Left over from a value that was a URL before somebody changed their mind.
            'url' => '/about',
            'new_tab' => 1,
            'rel' => ['nofollow', 'made-up'],
        ]);

        $this->assertSame(LinkTarget::Entity, $link->target);
        $this->assertSame(12, $link->entityId);
        $this->assertNull($link->url);
        $this->assertTrue($link->newTab);
        $this->assertSame(['nofollow'], $link->rel);
        $this->assertFalse($link->isEmpty());
    }

    #[Test]
    public function an_unfinished_choice_is_empty_whichever_kind_it_is(): void
    {
        $this->assertTrue(Link::fromArray(['target' => 'entity', 'entity_type' => 'page'])->isEmpty());
        $this->assertTrue(Link::fromArray(['target' => 'url', 'url' => '  '])->isEmpty());
        $this->assertTrue(Link::fromArray(['target' => 'none'])->isEmpty());
        $this->assertTrue(Link::fromArray(['target' => 'made-up'])->isEmpty());
    }

    /** `noopener noreferrer` is protection rather than a preference (§2, decision 8). */
    #[Test]
    public function a_new_tab_always_carries_noopener(): void
    {
        $this->assertSame(
            'nofollow noopener noreferrer',
            Link::fromArray(['target' => 'url', 'url' => '/x', 'new_tab' => true, 'rel' => ['nofollow']])->relAttribute(),
        );

        $this->assertSame('nofollow', Link::fromArray(['target' => 'url', 'url' => '/x', 'rel' => ['nofollow']])->relAttribute());
        $this->assertNull(Link::fromArray(['target' => 'url', 'url' => '/x'])->relAttribute());
    }

    /** A chosen page has nowhere to write an anchor, so the anchor is a field of the link. */
    #[Test]
    public function an_anchor_is_kept_without_its_hash_and_whichever_target_it_is(): void
    {
        $page = Link::fromArray(['target' => 'entity', 'entity_type' => 'page', 'entity_id' => 1, 'hash' => '#team']);

        $this->assertSame('team', $page->hash);
        $this->assertSame('#team', $page->fragment());

        $typed = Link::fromArray(['target' => 'url', 'url' => '/about', 'hash' => 'team']);

        $this->assertSame('team', $typed->hash);
    }

    /** One fragment in one place, or a link ends `#team#top`. */
    #[Test]
    public function an_address_typed_with_its_anchor_is_taken_apart(): void
    {
        $link = Link::fromArray(['target' => 'url', 'url' => '/about#team']);

        $this->assertSame('/about', $link->url);
        $this->assertSame('team', $link->hash);

        // What the field already holds wins: it is the one the editor can see.
        $both = Link::fromArray(['target' => 'url', 'url' => '/about#team', 'hash' => 'top']);

        $this->assertSame('/about', $both->url);
        $this->assertSame('top', $both->hash);
    }

    /** An anchor and nothing else points at the page the link is printed on. */
    #[Test]
    public function an_anchor_on_its_own_is_a_link(): void
    {
        $this->assertFalse(Link::fromArray(['target' => 'url', 'hash' => 'team'])->isEmpty());
        $this->assertTrue(Link::fromArray(['target' => 'entity', 'hash' => 'team'])->isEmpty());
    }

    #[Test]
    public function a_scheme_that_executes_is_not_an_address(): void
    {
        foreach (['/account', 'about/us', '#top', '?page=2', 'https://x.test/a', 'mailto:a@x.test', 'tel:+1', '//x.test/a'] as $url) {
            $this->assertTrue(Link::isAcceptableUrl($url), $url);
        }

        foreach (['javascript:alert(1)', 'JavaScript:alert(1)', 'data:text/html,<b>', ''] as $url) {
            $this->assertFalse(Link::isAcceptableUrl($url), $url);
        }
    }

    private function sources(): LinkSources
    {
        return $this->app->make(LinkSources::class);
    }

    /**
     * @param  list<LinkSource>  $sources
     * @return list<string>
     */
    private function types(array $sources): array
    {
        return array_map(static fn ($source): string => $source->type(), $sources);
    }
}
