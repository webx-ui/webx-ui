<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * A page's trail is its place in the tree (§17.5 of the SEO spec): the pages above it, then it,
 * the home put in front by `module-seo` — printed as the `BreadcrumbList` and as the crumbs.
 */
final class BreadcrumbsTest extends TestCase
{
    #[Test]
    public function a_page_is_trailed_by_the_pages_above_it(): void
    {
        $this->home()->publish();
        $about = $this->page('about');
        $this->page('team', $about);

        $page = (string) $this->get('/about/team')->assertOk()->getContent();

        $this->assertSame(['Home', 'About', 'Team'], $this->names($page));
        $this->assertStringEndsWith('/about', (string) $this->items($page)[1]['item']);
        $this->assertStringContainsString('<li><a href="http://localhost/about">About</a></li>', $page);
        $this->assertStringContainsString('<li aria-current="page">Team</li>', $page);
    }

    #[Test]
    public function a_draft_above_a_published_page_drops_out_rather_than_lead_to_a_404(): void
    {
        $draft = $this->page('about', published: false);
        $this->page('team', $draft);

        $page = (string) $this->get('/about/team')->assertOk()->getContent();

        $this->assertSame(['Home', 'Team'], $this->names($page));
        $this->assertStringNotContainsString('>About<', $page);
    }

    #[Test]
    public function a_site_can_switch_the_visible_trail_off_and_keep_the_breadcrumb_list(): void
    {
        config()->set('webx-pages.breadcrumbs', false);
        $this->home()->publish();
        $this->page('about');

        $page = (string) $this->get('/about')->assertOk()->getContent();

        $this->assertStringNotContainsString('webx-breadcrumbs', $page);
        $this->assertSame(['Home', 'About'], $this->names($page));
    }

    #[Test]
    public function the_home_page_has_no_trail(): void
    {
        $this->home()->publish();

        $page = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('BreadcrumbList', $page);
        $this->assertStringNotContainsString('webx-breadcrumbs', $page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $page): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $page, $matches);

        foreach ($matches[1] as $json) {
            $block = (array) json_decode($json, true);

            if (($block['@type'] ?? null) === 'BreadcrumbList') {
                /** @var list<array<string, mixed>> $items */
                $items = $block['itemListElement'];

                return $items;
            }
        }

        $this->fail('No BreadcrumbList on the page.');
    }

    /**
     * @return list<string>
     */
    private function names(string $page): array
    {
        return array_column($this->items($page), 'name');
    }
}
