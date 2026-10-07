<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Settings\Settings;

/**
 * What an outlet's page says about itself when nobody wrote it an SEO card: its name through the
 * site's title template, its summary, its logo — answered by the outlet, not typed into the view.
 */
final class SeoFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save([
            'seo.title-template' => '{title} — {site}',
            'general.project-name' => ['en' => 'Acme'],
            'seo.default-og' => ['path' => $this->file('media/ab/cd/default.jpg', 'image/jpeg')->path],
        ]);
    }

    #[Test]
    public function an_outlet_without_a_card_is_named_described_and_pictured_by_itself(): void
    {
        $logo = $this->file('media/ab/cd/tatler.png', 'image/png');
        $this->outlet('Tatler', [$this->row('One')], values: [
            'summary' => ['en' => 'A magazine.'],
            'logo' => ['path' => $logo->path],
        ]);

        $page = (string) $this->get('/press/tatler')->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, '<title>'));
        $this->assertSame(1, substr_count($page, '<meta name="description"'));
        $this->assertSame(1, substr_count($page, '<meta property="og:image"'));
        $this->assertStringContainsString('<title>Tatler — Acme</title>', $page);
        $this->assertStringContainsString('<meta property="og:title" content="Tatler — Acme">', $page);
        $this->assertStringContainsString('<meta name="description" content="A magazine.">', $page);
        $this->assertStringContainsString('<meta property="og:description" content="A magazine.">', $page);
        // The outlet's own logo, not the site's default social image.
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]*/tatler\.png[^"]*"#', $page);
        $this->assertStringNotContainsString('default.jpg', $page);
    }

    #[Test]
    public function without_a_logo_the_site_default_picture_stands(): void
    {
        $this->outlet('Tatler', [$this->row('One')]);

        $page = (string) $this->get('/press/tatler')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Tatler — Acme</title>', $page);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]*/default\.jpg[^"]*"#', $page);
    }
}
