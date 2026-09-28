<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Blocks\Tests\Fixtures\RegionPage;

/**
 * The draft of a region on a real page of the site (§6 of the regions spec): the page as a
 * visitor gets it, the region's draft in place of what is published, markers only around the
 * region, and a token that opens one region and nothing else.
 */
final class RegionsPreviewTest extends RegionTestCase
{
    #[Test]
    public function the_draft_of_the_region_stands_on_the_published_page_with_markers_only_around_it(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->publish('text', '<p class="b-text">{{ $text }}</p>');

        $region = $this->region('header', [$this->node('bar', ['text' => 'Published bar'], 'p1')]);
        $region->saveDraft(['blocks' => [$this->node('bar', ['text' => 'Draft bar'], 'd1')]]);

        $this->region('footer', [$this->node('text', ['text' => 'Footer words'], 'f1')]);

        $page = RegionPage::query()->create(['slug' => 'about', 'title' => 'About', 'blocks' => [$this->node('text', ['text' => 'Page body'], 'b1')]]);
        $page->publish();
        // A draft of the page, which this preview must not show.
        $page->saveDraft(['title' => 'About (draft)', 'blocks' => [$this->node('text', ['text' => 'Page draft'], 'b2')]]);

        $url = Preview::regionUrl('header', adminId: 7);
        $this->assertStringContainsString('/_preview/region/header?token=', $url);

        $response = $this->get($url.'&at=/about');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $html = (string) $response->getContent();

        $this->assertStringContainsString('<!--wx-region:header--><!--wx:d1--><div class="b-bar">Draft bar</div><!--/wx:d1--><!--/wx-region:header-->', $html);
        $this->assertStringNotContainsString('Published bar', $html);

        // The page as a visitor has it: published, without markers.
        $this->assertStringContainsString('<article data-page="About"><p class="b-text">Page body</p></article>', $html);
        $this->assertStringNotContainsString('Page draft', $html);

        // The other region as published, without markers either.
        $this->assertStringContainsString('<p class="b-text">Footer words</p>', $html);
        $this->assertStringNotContainsString('wx-region:footer', $html);
        $this->assertStringNotContainsString('<!--wx:f1-->', $html);

        // And the row is untouched.
        $this->assertSame('Published bar', $region->refresh()->blocks[0]['values']['text'] ?? null);
    }

    #[Test]
    public function the_front_page_is_where_the_region_is_drawn_unless_the_link_says_otherwise(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->region('header', [$this->node('bar', ['text' => 'Draft bar'], 'd1')], published: false);

        RegionPage::query()->create(['slug' => '', 'title' => 'Home'])->publish();

        $this->get(Preview::regionUrl('header'))
            ->assertOk()
            ->assertSee('<article data-page="Home">', false)
            ->assertSee('Draft bar');
    }

    #[Test]
    public function a_token_for_one_region_opens_no_other(): void
    {
        $token = (string) parse_url(Preview::regionUrl('header'), PHP_URL_QUERY);

        $this->get('/_preview/region/footer?'.$token)->assertForbidden();
        $this->get('/_preview/region/header?token=nonsense')->assertForbidden();
        $this->get('/_preview/region/header')->assertForbidden();

        // A region the site does not declare has nothing to preview.
        $this->get(Preview::regionUrl('sidebar'))->assertNotFound();
    }

    #[Test]
    public function an_unpublished_page_stays_a_404_under_the_region_token(): void
    {
        RegionPage::query()->create(['slug' => 'secret', 'title' => 'Secret']);

        $this->get(Preview::regionUrl('header', at: '/secret'))->assertNotFound();
    }

    #[Test]
    public function an_address_the_registry_does_not_know_draws_the_region_on_the_stage(): void
    {
        $this->app['config']->set('webx-blocks.layout', 'region-site::layout');

        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->region('header', [$this->node('bar', ['text' => 'Draft bar'], 'd1')], published: false);

        $html = (string) $this->get(Preview::regionUrl('header', at: '/no-such-page'))->assertOk()->getContent();

        $this->assertStringContainsString('/no-such-page is not a page of the site', $html);
        $this->assertStringContainsString('<!--wx-region:header--><!--wx:d1--><div class="b-bar">Draft bar</div>', $html);
        // Drawn once: by the layout, not a second time in the stage's place.
        $this->assertSame(1, substr_count($html, 'Draft bar'));
    }

    #[Test]
    public function in_the_preview_a_failing_block_is_shown_with_a_strip_above_the_region(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->publish('bomb', '<p>@if ($boom) {{ throw new RuntimeException(\'Boom\') }} @endif fine</p>');
        $this->region('header', [$this->node('bar', ['text' => 'Menu'], 'm1'), $this->node('bomb', ['boom' => true], 'x1')], published: false);

        RegionPage::query()->create(['slug' => '', 'title' => 'Home'])->publish();

        $html = (string) $this->get(Preview::regionUrl('header'))->assertOk()->getContent();

        $this->assertStringContainsString('data-wx-region-error', $html);
        $this->assertStringContainsString('data-wx-block-error="x1"', $html);
        $this->assertStringContainsString('Menu', $html);
        $this->assertStringNotContainsString('Header from code', $html);
    }

    #[Test]
    public function an_empty_region_shows_its_fallback_between_the_markers(): void
    {
        RegionPage::query()->create(['slug' => '', 'title' => 'Home'])->publish();

        $html = (string) $this->get(Preview::regionUrl('header'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<!--wx-region:header--><header class="from-code"[^>]*>Header from code</header>\s*<!--/wx-region:header-->#', $html);
    }

    #[Test]
    public function the_token_is_the_way_past_the_password_over_a_site_in_testing(): void
    {
        $this->app['config']->set('webx-admin.gate.enabled', true);
        $this->app['config']->set('webx-admin.gate.users', 'client:secret');

        RegionPage::query()->create(['slug' => '', 'title' => 'Home'])->publish();

        $this->get(Preview::regionUrl('header'))->assertOk();
        $this->get('/_preview/region/header?token=nonsense')->assertStatus(401);
    }
}
