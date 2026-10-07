<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Settings\Settings;

/**
 * What the blog's pages are called when nobody wrote them an SEO card: the entity's own title,
 * lead and cover, through the site's title template — rather than a bare `<title>` the view
 * printed on its own, which missed the template, `og:title` and every picture.
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
    public function an_article_without_a_card_speaks_with_its_title_lead_and_cover(): void
    {
        $article = $this->article('changing-a-belt');
        $article->forceFill(['lead' => ['en' => '<p>Five steps, <b>one</b> spanner.</p>'], 'cover_id' => $this->picture()->getKey()])->save();
        $article->publish();

        $response = $this->get('/blog/changing-a-belt')->assertOk();

        $response->assertSee('<title>Changing a belt — Acme</title>', false);
        $response->assertSee('<meta property="og:title" content="Changing a belt — Acme">', false);
        $response->assertSee('<meta property="og:description" content="Five steps, one spanner.">', false);
        $this->assertMatchesRegularExpression('~<meta property="og:image" content="https?://[^"]+/covers/belts\.jpg[^"]*">~', $response->getContent());
    }

    #[Test]
    public function an_article_is_an_article_to_a_social_network_with_its_dates_rubric_and_tags(): void
    {
        $article = $this->article('changing-a-belt');
        $article->rubrics()->attach([$this->rubric('repairs')->id => ['position' => 0]]);
        $article->tags()->attach([$this->tag('belts')->id, $this->tag('tools')->id]);
        $article->publish();

        $body = (string) $this->get('/blog/changing-a-belt')->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:type" content="article">', $body);
        $this->assertMatchesRegularExpression('~<meta property="article:published_time" content="\d{4}-\d{2}-\d{2}T[^"]+">~', $body);
        $this->assertMatchesRegularExpression('~<meta property="article:modified_time" content="\d{4}-\d{2}-\d{2}T[^"]+">~', $body);
        $this->assertStringContainsString('<meta property="article:section" content="Repairs">', $body);
        $this->assertStringContainsString('<meta property="article:tag" content="Belts">', $body);
        $this->assertStringContainsString('<meta property="article:tag" content="Tools">', $body);

        // The feed is a listing, not an article.
        $this->assertStringContainsString('<meta property="og:type" content="website">', (string) $this->get(route('webx.blog.feed'))->getContent());
    }

    #[Test]
    public function the_sites_default_picture_does_not_replace_the_articles_cover(): void
    {
        // Stored the way the panel stores it: a library key, which the setting turns into an address.
        $this->picture('defaults/default.png');
        app(Settings::class)->save(['seo.default-og' => ['path' => 'defaults/default.png']]);

        $article = $this->article('changing-a-belt');
        $article->forceFill(['cover_id' => $this->picture()->getKey()])->save();
        $article->publish();

        $body = (string) $this->get('/blog/changing-a-belt')->getContent();

        $this->assertStringContainsString('/covers/belts.jpg', $body);
        $this->assertStringNotContainsString('default.png', $body);

        // An article with no cover of its own does get the site's picture.
        $this->article('no-cover');
        $this->assertMatchesRegularExpression('~<meta property="og:image" content="[^"]+/defaults/default\.png[^"]*">~', (string) $this->get('/blog/no-cover')->getContent());
    }

    #[Test]
    public function a_rubric_and_a_tag_are_called_by_their_titles(): void
    {
        $rubric = $this->rubric('repairs');
        $rubric->forceFill(['lead' => ['en' => 'How to fix things.'], 'cover_id' => $this->picture()->getKey()])->save();
        $this->tag('belts');

        $page = $this->get($rubric->url())->assertOk();
        $page->assertSee('<title>Repairs — Acme</title>', false);
        $page->assertSee('<meta property="og:description" content="How to fix things.">', false);
        $page->assertSee('/covers/belts.jpg', false);

        $this->get('/blog/tag/belts')->assertSee('<title>Belts — Acme</title>', false);
    }

    #[Test]
    public function the_feed_is_called_by_the_word_the_view_hands_in(): void
    {
        $title = trans('webx-blog::blog.title');

        $this->get(route('webx.blog.feed'))
            ->assertOk()
            ->assertSee('<title>'.e($title).' — Acme</title>', false)
            ->assertSee('<meta property="og:title" content="'.e($title).' — Acme">', false);
    }

    private function picture(string $path = 'covers/belts.jpg'): MediaFile
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
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
        ]);

        return $file;
    }
}
