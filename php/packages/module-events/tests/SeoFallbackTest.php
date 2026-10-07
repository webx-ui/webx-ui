<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Settings\Settings;

/**
 * What a page says about itself when nobody wrote it an SEO card: the event's or the category's
 * own name through the site's title template, its lead, its own picture — and the index called by
 * the section's name the same way.
 */
final class SeoFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save([
            'seo.title-template' => '{title} — {site}',
            'general.project-name' => ['en' => 'Acme'],
            'seo.default-og' => ['path' => $this->picture('media/ab/cd/default.jpg')->path],
        ]);
    }

    #[Test]
    public function an_event_without_a_card_is_named_described_and_pictured_by_itself(): void
    {
        $this->event('spring-class', attributes: [
            'lead' => 'Cooking in spring.',
            'gallery' => [['path' => $this->picture()->path]],
        ]);

        $page = (string) $this->get('/events/spring-class')->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, '<title>'));
        $this->assertStringContainsString('<title>Spring class — Acme</title>', $page);
        $this->assertStringContainsString('<meta property="og:title" content="Spring class — Acme">', $page);
        $this->assertStringContainsString('<meta name="description" content="Cooking in spring.">', $page);
        $this->assertStringContainsString('<meta property="og:description" content="Cooking in spring.">', $page);
        // The event's own cover, not the site's default social image: that one is for a page
        // with no picture of its own.
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]*/class\.jpg[^"]*"#', $page);
        $this->assertStringNotContainsString('default.jpg', $page);
    }

    #[Test]
    public function a_category_is_named_by_itself_and_the_index_by_the_section(): void
    {
        $category = $this->category('workshops');
        $category->update([
            'lead' => '<p>Hands on.</p>',
            'cover' => ['path' => $this->picture('media/ab/cd/workshops.jpg')->path],
        ]);

        $page = (string) $this->get($category->url())->assertOk()->getContent();
        $index = (string) $this->get('/events')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Workshops — Acme</title>', $page);
        $this->assertStringContainsString('<meta name="description" content="Hands on.">', $page);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]*/workshops\.jpg[^"]*"#', $page);
        $this->assertStringContainsString('<title>Events — Acme</title>', $index);
        $this->assertStringContainsString('<meta property="og:title" content="Events — Acme">', $index);
    }
}
