<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Settings\Settings;

/**
 * What a page says about itself when nobody wrote it an SEO card: the recipe's or the category's
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
    public function a_recipe_without_a_card_is_named_described_and_pictured_by_itself(): void
    {
        $photo = $this->picture();
        $this->recipe('porridge', attributes: [
            'lead' => 'Warm and quick.',
            'gallery' => [['path' => $photo->path]],
        ]);

        $page = (string) $this->get('/recipes/porridge')->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, '<title>'));
        $this->assertStringContainsString('<title>Porridge — Acme</title>', $page);
        $this->assertStringContainsString('<meta property="og:title" content="Porridge — Acme">', $page);
        $this->assertStringContainsString('<meta name="description" content="Warm and quick.">', $page);
        $this->assertStringContainsString('<meta property="og:description" content="Warm and quick.">', $page);
        // The recipe's own photo, not the site's default social image: that one is for a page
        // with no picture of its own.
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]*/porridge\.jpg[^"]*"#', $page);
        $this->assertStringNotContainsString('default.jpg', $page);
    }

    #[Test]
    public function a_card_beats_the_fallback_field_by_field(): void
    {
        $recipe = $this->recipe('porridge', attributes: ['lead' => 'Warm and quick.']);
        $recipe->saveSeo(['title' => ['en' => 'The best porridge']]);

        $page = (string) $this->get('/recipes/porridge')->assertOk()->getContent();

        $this->assertStringContainsString('<title>The best porridge — Acme</title>', $page);
        $this->assertStringContainsString('<meta name="description" content="Warm and quick.">', $page);
        // No photo of its own: the site's default stands.
        $this->assertStringContainsString('default.jpg', $page);
    }

    #[Test]
    public function a_category_is_named_by_itself_and_the_index_by_the_section(): void
    {
        $this->category('breakfasts')->update([
            'lead' => '<p>To start the day.</p>',
            'cover' => ['path' => $this->picture('media/ab/cd/breakfasts.jpg')->path],
        ]);

        $category = (string) $this->get('/recipes/breakfasts')->assertOk()->getContent();
        $index = (string) $this->get('/recipes')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Breakfasts — Acme</title>', $category);
        // The lead is rich text: the markup stays on the page and out of the description.
        $this->assertStringContainsString('<meta name="description" content="To start the day.">', $category);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]*/breakfasts\.jpg[^"]*"#', $category);
        $this->assertStringContainsString('<title>Recipes — Acme</title>', $index);
        $this->assertStringContainsString('<meta property="og:title" content="Recipes — Acme">', $index);
    }
}
