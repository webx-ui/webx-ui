<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Settings\Settings;

/**
 * What a brand's page and the list of brands are called when nobody wrote them an SEO card: the
 * brand's own name, description and logo, through the site's title template — rather than a bare
 * `<title>` the view printed on its own, which missed the template, `og:title` and the logo.
 */
final class SeoFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save([
            'general.project-name' => ['en' => 'Acme'],
            'seo.title-template' => '{title} — {site}',
        ]);
    }

    #[Test]
    public function a_brand_without_a_card_speaks_with_its_name_description_and_logo(): void
    {
        $apple = $this->brand('Apple', [
            'description' => ['en' => '<p>Think <b>different</b> things.</p>'],
            'logo_id' => $this->mediaFile('logos/apple.png')->getKey(),
        ]);
        $this->product('MacBook', $apple);

        $response = $this->get('/brands/apple')->assertOk();

        $response->assertSee('<title>Apple — Acme</title>', false);
        $response->assertSee('<meta property="og:title" content="Apple — Acme">', false);
        $response->assertSee('<meta property="og:description" content="Think different things.">', false);
        $this->assertMatchesRegularExpression('~<meta property="og:image" content="https?://[^"]+/logos/apple\.png[^"]*">~', (string) $response->getContent());
    }

    #[Test]
    public function the_sites_default_picture_does_not_replace_the_brands_logo(): void
    {
        $this->mediaFile('defaults/default.png');
        app(Settings::class)->save(['seo.default-og' => ['path' => 'defaults/default.png']]);

        $apple = $this->brand('Apple', ['logo_id' => $this->mediaFile('logos/apple.png')->getKey()]);
        $this->product('MacBook', $apple);

        $body = (string) $this->get('/brands/apple')->getContent();

        $this->assertStringContainsString('/logos/apple.png', $body);
        $this->assertStringNotContainsString('default.png', $body);
    }

    #[Test]
    public function the_list_of_brands_is_called_by_the_word_the_view_hands_in(): void
    {
        $this->brand('Apple');
        $title = e(__('webx-catalog-brands::module.title'));

        $this->get('/brands')
            ->assertOk()
            ->assertSee("<title>{$title} — Acme</title>", false)
            ->assertSee("<meta property=\"og:title\" content=\"{$title} — Acme\">", false);
    }

    private function mediaFile(string $path): MediaFile
    {
        // Every file belongs to a folder, and the library's migration makes the root one.
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        /** @var MediaFile $file */
        $file = MediaFile::query()->forceCreate([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => md5($path),
            'name' => basename($path),
            'name_lower' => basename($path),
            'file_name' => basename($path),
            'extension' => 'png',
            'mime' => 'image/png',
            'size' => 2048,
        ]);

        return $file;
    }
}
