<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Pages\Links\PageLinkSource;

/**
 * Pages as something to link to (§13 of the menu spec).
 */
final class LinkSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `app.url` alone does not reach a generator that was already built, and what these
        // assertions are about is the path after the host.
        $this->app['url']->forceRootUrl('https://example.test');
        $this->app['url']->forceScheme('https');
    }

    #[Test]
    public function the_module_registers_itself_with_the_panel(): void
    {
        $source = $this->app->make(LinkSources::class)->find('page');

        $this->assertInstanceOf(PageLinkSource::class, $source);
        $this->assertSame('pages.view', $source->permission());
    }

    #[Test]
    public function search_finds_a_page_by_its_name_and_by_its_slug(): void
    {
        $this->page('about');
        $this->page('pricing');

        $this->assertSame(['About'], $this->titles($this->source()->search('abou', 'en', 20)));
        $this->assertSame(['Pricing'], $this->titles($this->source()->search('pricing', 'en', 20)));
    }

    /** The list draws the title a page has, so a page named in one language is found from another. */
    #[Test]
    public function a_page_titled_in_another_language_is_still_found(): void
    {
        $this->useLocales('en', 'uk');

        $page = $this->page('about');
        $page->setTranslation('title', 'uk', 'Про нас')->save();

        $this->assertSame(['Про нас'], $this->titles($this->source()->search('Про', 'uk', 20)));
    }

    #[Test]
    public function an_empty_query_answers_with_the_tree_from_the_top(): void
    {
        $this->page('about');

        $titles = $this->titles($this->source()->search('', 'en', 20));

        $this->assertSame([(string) __('webx-pages::pages.home'), 'About'], $titles);
    }

    #[Test]
    public function a_published_page_with_an_address_is_available(): void
    {
        $page = $this->page('about');

        $candidate = $this->resolve($page->getKey());

        $this->assertSame('About', $candidate->title);
        $this->assertSame('https://example.test/about', $candidate->url);
        $this->assertTrue($candidate->available);
    }

    /**
     * The registry holds an address for a draft too — a slug is content and exists before the page
     * is published — so anything that read only `routes` would link to a page the site 404s.
     */
    #[Test]
    public function a_draft_has_an_address_and_is_not_available(): void
    {
        $page = $this->page('about', published: false);

        $candidate = $this->resolve($page->getKey());

        $this->assertSame('https://example.test/about', $candidate->url);
        $this->assertFalse($candidate->available);
    }

    #[Test]
    public function a_page_with_no_address_in_this_language_has_neither(): void
    {
        $this->useLocales('en', 'uk');

        $page = $this->page('about');

        $candidate = $this->resolve($page->getKey(), 'uk');

        $this->assertNull($candidate->url);
        $this->assertFalse($candidate->available);
    }

    /** Three pages called "Pricing" are one word until you can see which branch each is in. */
    #[Test]
    public function the_hint_is_the_line_of_titles_above_the_page(): void
    {
        $services = $this->page('services');
        $courses = $this->page('courses');

        $under = $this->page('pricing', $services);
        $other = $this->page('pricing', $courses);
        $deep = $this->page('basic', $under);

        $this->assertSame('Services', $this->resolve($under->getKey())->hint);
        $this->assertSame('Courses', $this->resolve($other->getKey())->hint);
        $this->assertSame('Services / Pricing', $this->resolve($deep->getKey())->hint);
        // Everything on the site is inside the home page, so saying so says nothing.
        $this->assertNull($this->resolve($services->getKey())->hint);
    }

    #[Test]
    public function resolving_an_id_that_is_gone_leaves_out_the_key(): void
    {
        $page = $this->page('about');

        $resolved = $this->source()->resolve([(int) $page->getKey(), 9999], 'en');

        $this->assertArrayHasKey((int) $page->getKey(), $resolved);
        $this->assertArrayNotHasKey(9999, $resolved);
    }

    #[Test]
    public function a_page_in_the_bin_is_not_offered(): void
    {
        $page = $this->page('about');
        $page->delete();

        $this->assertSame([], $this->source()->resolve([(int) $page->getKey()], 'en'));
        $this->assertSame(
            [(string) __('webx-pages::pages.home')],
            $this->titles($this->source()->search('', 'en', 20)),
        );
    }

    private function source(): PageLinkSource
    {
        return $this->app->make(PageLinkSource::class);
    }

    private function resolve(int|string $id, string $locale = 'en'): LinkCandidate
    {
        $candidate = $this->source()->resolve([(int) $id], $locale)[(int) $id] ?? null;

        $this->assertInstanceOf(LinkCandidate::class, $candidate);

        return $candidate;
    }

    /**
     * @param  list<LinkCandidate>  $candidates
     * @return list<string>
     */
    private function titles(array $candidates): array
    {
        return array_map(static fn (LinkCandidate $candidate): string => $candidate->title, $candidates);
    }
}
