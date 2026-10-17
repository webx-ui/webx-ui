<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Blocks\Models\Block;
use WebxUi\Cache\CacheGroups;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Pages\Models\Page;

/**
 * The query budget of every public page of the module, and the freshness of what they read
 * (WEBX_UI_PERFORMANCE_AUDIT.md §7).
 *
 * Every budget is a ceiling the page fits under today with a tree of thirty-odd pages five
 * levels deep in two languages, not a target, and the point of each is that it does not grow
 * with the rows: a page that asks the registry once per ancestor is caught by
 * `assertNoRepeatedQueries()` on the warm pass whatever the count. A change that needs more says
 * why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    /** Six sections of four pages each under the home, plus the chain down to the fifth level. */
    private const SECTIONS = 6;

    private const PER_SECTION = 4;

    /** The deepest page of the tree: the fifth level, the audit's `/about` of depth 5. */
    private const DEEP = '/about/team/people/anna/story';

    /** The same page in the second language: every slug above it translated too. */
    private const DEEP_UK = '/uk/pro-nas/komanda/lyudy/anna/istoriya';

    /** The page with a dozen blocks, pictures among them: the home page. */
    private const WIDE = '/';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The library too, so that a block can hold a picture the way the demo's do.
        return [...parent::getPackageProviders($app), MediaServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them: the budget of a warm page is the point.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function the_home_page_fits_its_budget_cold_and_warm(): void
    {
        $this->seedTree();

        $this->cold();
        $this->assertQueriesWithin(16, fn () => $this->get(self::WIDE)->assertOk());

        // The page, its card, its addresses, the picture of its blocks and their bundle.
        $this->assertQueriesWithin(8, fn () => $this->get(self::WIDE)->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get(self::WIDE)->assertOk());
    }

    #[Test]
    public function a_page_five_levels_deep_fits_its_budget_cold_and_warm(): void
    {
        $this->seedTree();

        $this->cold();
        $this->assertQueriesWithin(18, fn () => $this->get(self::DEEP)->assertOk());

        // The ancestors once, with their addresses in one query — not one per step of the trail.
        $this->assertQueriesWithin(9, fn () => $this->get(self::DEEP)->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get(self::DEEP)->assertOk());
    }

    #[Test]
    public function a_translated_page_fits_its_budget_in_its_second_language(): void
    {
        $this->seedTree();

        $this->cold();
        $this->assertQueriesWithin(18, fn () => $this->get(self::DEEP_UK)->assertOk());

        $this->assertQueriesWithin(9, fn () => $this->get(self::DEEP_UK)->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get(self::DEEP_UK)->assertOk());
    }

    #[Test]
    public function the_sitemap_fits_its_budget(): void
    {
        $this->seedTree();

        // The build: every page in every language, chunked, with the cards and the addresses of
        // a chunk in one query each — the `hreflang` set and the untranslated check read them.
        $this->cold();
        $this->assertQueriesWithin(20, fn () => $this->get('/sitemap.xml')->assertOk());
        // Served from the cache: nothing but the request's own infrastructure.
        $this->assertQueriesWithin(4, fn () => $this->get('/sitemap-page.xml')->assertOk());
    }

    /**
     * Nothing a page reads is kept past the rows it was built from: a save through the panel
     * reaches the next request in the same process, the way a worker serves it — the new title,
     * the old address answering with a redirect, and the trail of a branch moved elsewhere.
     */
    #[Test]
    public function a_page_shows_a_change_made_through_the_panel_at_once(): void
    {
        $this->seedTree();
        $editor = $this->editor();

        $this->get('/section-1/page-1-1')->assertOk()->assertSee('<title>Page-1-1</title>', false);

        $page = Page::query()->where('slug->en', 'page-1-1')->firstOrFail();
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($page->getKey()), ['values' => ['title' => ['en' => 'The first, renamed'], 'slug' => ['en' => 'the-first']]])
            ->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->api($page->getKey().'/publish'))->assertOk();

        $this->get('/section-1/the-first')->assertOk()->assertSee('<title>The first, renamed</title>', false);
        $this->get('/section-1/page-1-1')->assertRedirect('/section-1/the-first');

        // The whole chain under `about` goes under the first section: every address below it
        // is rewritten, and the trail of its deepest page names the section first.
        $this->get(self::DEEP)->assertOk()->assertDontSee('>Section-1<', false);

        $about = Page::query()->where('slug->en', 'about')->firstOrFail();
        $section = Page::query()->where('slug->en', 'section-1')->firstOrFail();
        $this->actingAs($editor, 'cms')
            ->postJson($this->api($about->getKey()).'/move', ['target' => $section->getKey(), 'zone' => 'inside'])
            ->assertOk();

        $moved = (string) $this->get('/section-1'.self::DEEP)->assertOk()->getContent();

        $this->assertStringContainsString('href="http://localhost/section-1">Section-1</a>', $moved);
        $this->assertStringContainsString('href="http://localhost/section-1/about">About</a>', $moved);
        $this->assertStringContainsString('href="http://localhost/section-1/about/team/people/anna">Anna</a>', $moved);
        $this->get(self::DEEP)->assertRedirect('/section-1'.self::DEEP);
    }

    /**
     * The home page with a dozen blocks, two with a picture, and a tree under it: six sections
     * of four pages, and under the first section the chain down to the fifth level. Every page
     * has a slug in both languages, so the `hreflang` lines are printed, and a text block; the
     * deepest has an SEO card the way the demo's pages do.
     */
    private function seedTree(): void
    {
        $this->useLocales('en', 'uk');

        $this->blockType('text', '<p>{{ $text }}</p>');
        $this->pictureBlockType();
        $cover = $this->picture();

        $home = $this->home();
        $blocks = [];

        for ($i = 1; $i <= 12; $i++) {
            $blocks[] = $i % 6 === 0
                ? ['key' => "b{$i}", 'type' => 'media-text', 'values' => ['image' => ['path' => $cover->path, 'alt' => 'A cover'], 'heading' => ['en' => "Heading {$i}", 'uk' => "Заголовок {$i}"]]]
                : ['key' => "b{$i}", 'type' => 'text', 'values' => ['text' => "Paragraph {$i}"]];
        }

        $home->blocks = $blocks;
        $home->save();
        $home->publish();
        $home->saveSeo(['title' => ['en' => 'A site of pages', 'uk' => 'Сайт зі сторінок'], 'description' => ['en' => 'The home page.']]);

        for ($s = 1; $s <= self::SECTIONS; $s++) {
            $section = $this->translated("section-{$s}", "rozdil-{$s}", $home);

            for ($p = 1; $p <= self::PER_SECTION; $p++) {
                $this->translated("page-{$s}-{$p}", "storinka-{$s}-{$p}", $section);
            }
        }

        $node = $this->translated('about', 'pro-nas', $home);

        foreach ([['team', 'komanda'], ['people', 'lyudy'], ['anna', 'anna'], ['story', 'istoriya']] as [$en, $uk]) {
            $node = $this->translated($en, $uk, $node);
        }

        $node->saveSeo(['title' => ['en' => 'The story of Anna', 'uk' => 'Історія Анни'], 'description' => ['en' => 'Five levels down.']]);
    }

    /** A published page with a slug in both languages and a text block, under `$parent`. */
    private function translated(string $en, string $uk, Page $parent): Page
    {
        $page = new Page([
            'title' => ['en' => ucfirst($en), 'uk' => ucfirst($uk)],
            'slug' => ['en' => $en, 'uk' => $uk],
            'blocks' => [['key' => 'a', 'type' => 'text', 'values' => ['text' => "The text of {$en}."]]],
        ]);
        $page->appendTo($parent);
        $page->publish();

        return $page->refresh();
    }

    /** A block type with a picture and a translated heading, the shape of the theme's `media-text`. */
    private function pictureBlockType(): Block
    {
        $block = Block::query()->create(['slug' => 'media-text', 'title' => 'Picture and text']);

        $block->saveVersion([
            'template' => '<section><img src="{{ $image[\'url\'] ?? \'\' }}" alt="{{ $image[\'alt\'] ?? \'\' }}"><h2>{{ $heading }}</h2></section>',
            'schema' => [
                ['id' => 'image', 'type' => 'wx-media', 'label' => 'Picture'],
                ['id' => 'heading', 'type' => 'wx-input', 'label' => 'Heading', 'localized' => true],
            ],
        ]);
        $block->publish();

        return $block->refresh();
    }

    private function picture(): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/cover.jpg',
            'hash' => str_repeat('a', 32),
            'name' => 'cover',
            'file_name' => 'cover.jpg',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ]);
    }

    /** The first visitor after a deploy: an empty store, and nothing read in this process either. */
    private function cold(): void
    {
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
    }
}
