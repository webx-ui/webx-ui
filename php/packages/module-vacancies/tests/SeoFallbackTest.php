<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Settings\Settings;

/**
 * What a page says about itself when nobody wrote it an SEO card: the vacancy's own name through
 * the site's title template and its lead — and the index called by the section's name the same way.
 */
final class SeoFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save([
            'seo.title-template' => '{title} — {site}',
            'general.project-name' => ['en' => 'Acme'],
        ]);
    }

    #[Test]
    public function a_vacancy_without_a_card_is_named_and_described_by_itself(): void
    {
        $this->vacancy('designer', attributes: ['lead' => 'Draw the clinic’s new face.']);

        $page = (string) $this->get('/careers/designer')->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, '<title>'));
        $this->assertStringContainsString('<title>Designer — Acme</title>', $page);
        $this->assertStringContainsString('<meta property="og:title" content="Designer — Acme">', $page);
        $this->assertStringContainsString('<meta name="description" content="Draw the clinic’s new face.">', $page);
        $this->assertStringContainsString('<meta property="og:description" content="Draw the clinic’s new face.">', $page);
    }

    #[Test]
    public function the_index_is_called_by_the_section(): void
    {
        $page = (string) $this->get('/careers')->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, '<title>'));
        $this->assertStringContainsString('<title>Careers — Acme</title>', $page);
        $this->assertStringContainsString('<meta property="og:title" content="Careers — Acme">', $page);
    }
}
