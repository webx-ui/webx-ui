<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Settings\Settings;

/**
 * What a page says about itself when nobody wrote it an SEO card: the service's or the category's
 * own name through the site's title template, its lead, its cover — and the index called by the
 * section's name the same way.
 */
final class SeoFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save([
            'seo.title-template' => '{title} — {site}',
            'general.project-name' => ['en' => 'Acme'],
            'seo.default-og' => ['path' => $this->picture('default')->path],
        ]);
    }

    #[Test]
    public function a_service_without_a_card_is_named_described_and_pictured_by_itself(): void
    {
        $service = $this->service('crowns');
        $service->update(['lead' => 'Ceramic crowns in a day.', 'cover_id' => $this->picture('crowns')->getKey()]);
        $service->publish();

        $page = (string) $this->get('/services/crowns')->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, '<title>'));
        $this->assertStringContainsString('<title>Crowns — Acme</title>', $page);
        $this->assertStringContainsString('<meta property="og:title" content="Crowns — Acme">', $page);
        $this->assertStringContainsString('<meta name="description" content="Ceramic crowns in a day.">', $page);
        $this->assertStringContainsString('<meta property="og:description" content="Ceramic crowns in a day.">', $page);
        // The service's own cover, not the site's default social image.
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]*/crowns\.jpg[^"]*"#', $page);
        $this->assertStringNotContainsString('default.jpg', $page);
    }

    #[Test]
    public function a_category_is_named_by_itself_and_the_index_by_the_section(): void
    {
        $category = $this->category('prosthetics');
        $category->update(['lead' => '<p>New teeth.</p>', 'cover_id' => $this->picture('prosthetics')->getKey()]);

        $page = (string) $this->get($category->url())->assertOk()->getContent();
        $index = (string) $this->get('/services')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Prosthetics — Acme</title>', $page);
        $this->assertStringContainsString('<meta name="description" content="New teeth.">', $page);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]*/prosthetics\.jpg[^"]*"#', $page);
        $this->assertStringContainsString('<title>Services — Acme</title>', $index);
        $this->assertStringContainsString('<meta property="og:title" content="Services — Acme">', $index);
    }

    /** A picture in the library, the way an upload leaves one. */
    private function picture(string $name): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => "media/ab/cd/{$name}.jpg",
            'hash' => md5($name),
            'name' => $name,
            'file_name' => "{$name}.jpg",
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ]);
    }
}
