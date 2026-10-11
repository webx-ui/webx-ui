<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * The demo content of this package, seeded and taken out again.
 *
 * The half worth a test is the asymmetry: the child page is created and disappears, while the
 * home page comes from a migration, is filled in, and has to come back empty rather than not
 * come back at all. `module-admin` tests the command; this tests what the command leaves
 * behind on a site that really has pages.
 */
final class DemoTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_fills_the_home_page_and_adds_one_under_it(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        // The block types come first although the panel lists them last: the pages are made
        // of them.
        $this->assertSame(3, Block::query()->whereNotNull('published_version_id')->count());

        $home = $this->home();

        $this->assertNotSame([], $home->blocksTree());
        $this->assertTrue($home->isPublished());

        $about = Page::query()->where('parent_id', $home->getKey())->first();

        $this->assertInstanceOf(Page::class, $about);
        $this->assertSame('about', (string) $about->slug);
        $this->assertTrue($about->isPublished());
        $this->assertSame('about', (string) $about->routeCanonical()?->path);

        // Without a card a page has no title at all — the module never invents one from the
        // name in the tree — and an untitled front page is the wrong first impression.
        $this->assertSame('A site made of blocks', $home->seoData()?->title);
        $this->assertSame('About this demo', $about->seoData()?->title);
    }

    #[Test]
    public function a_translated_document_gives_the_page_every_language_the_site_has(): void
    {
        $this->useLocales('ru', 'en', 'de');

        $this->artisan('webx:demo')->assertSuccessful();

        $about = Page::query()->where('parent_id', $this->home()->getKey())->firstOrFail();

        // Russian and English, each at its own address; the Polish the document also brings is
        // not a language of this site, and German is not in the document at all.
        $this->assertEquals(['ru' => 'О сайте', 'en' => 'About'], $about->getTranslations('title'));
        $this->assertSame('o-saite', $about->routePath('ru'));
        $this->assertSame('about', $about->routePath('en'));
        $this->assertEqualsCanonicalizing(['ru', 'en'], Route::query()->forEntity($about)->pluck('locale')->all());
        $this->assertEqualsCanonicalizing(['ru', 'en'], array_keys($about->seo()->firstOrFail()->getTranslations('title')));

        // A field of a block keeps a map of the same languages, read in the one being shown.
        $this->assertEqualsCanonicalizing(['ru', 'en'], array_keys($about->blocksTree()[0]['values']['heading']));
    }

    #[Test]
    public function a_document_in_one_language_is_the_default_language_of_any_site(): void
    {
        // German first: the document has no German, so its English stands in for it — as the one
        // string of a document written before translations always did.
        $this->useLocales('de', 'en');

        $this->artisan('webx:demo')->assertSuccessful();

        $about = Page::query()->where('parent_id', $this->home()->getKey())->firstOrFail();

        $this->assertSame(['de' => 'About', 'en' => 'About'], $about->getTranslations('title'));
        $this->assertSame('about', $about->routePath('de'));
    }

    #[Test]
    public function a_theme_replaces_a_document_and_adds_its_own_pages_with_their_children(): void
    {
        $theme = sys_get_temp_dir().'/webx-pages-demo-theme-'.getmypid();
        @mkdir($theme.'/demo/pages', 0777, true);
        file_put_contents($theme.'/demo/pages/about.json', (string) json_encode(['title' => 'Studio', 'slug' => 'studio']));
        file_put_contents($theme.'/demo/pages/showcase.json', (string) json_encode([
            'title' => 'Showcase',
            'slug' => 'showcase',
            'children' => [['title' => 'Long words', 'slug' => 'long-words']],
        ]));

        try {
            $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('acme/theme', $theme, false)]));

            $this->artisan('webx:demo')->assertSuccessful();

            $home = $this->home();
            $under = Page::query()->where('parent_id', $home->getKey())->orderBy('id')->get();

            $this->assertSame(['studio', 'showcase'], array_map(fn (Page $page): string => (string) $page->slug, $under->all()), 'The theme\'s about stands in for the module\'s; its showcase comes after.');

            $words = Page::query()->where('parent_id', $under->all()[1]->getKey())->first();
            $this->assertSame('showcase/long-words', (string) $words?->routeCanonical()?->path);

            $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();
            $this->assertSame(1, Page::withTrashed()->count(), 'The theme\'s pages go with the rest of the demo.');
        } finally {
            array_map('unlink', glob($theme.'/demo/pages/*.json') ?: []);
            @rmdir($theme.'/demo/pages');
            @rmdir($theme.'/demo');
            @rmdir($theme);
        }
    }

    #[Test]
    public function the_seo_rule_is_skipped_without_the_blog_it_is_written_for(): void
    {
        $this->artisan('webx:demo')
            ->expectsOutputToContain('seo skipped: no articles.')
            ->assertSuccessful();
    }

    #[Test]
    public function removing_leaves_the_home_page_where_it_was_and_empty(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $home = $this->home();

        $this->assertSame([], $home->blocksTree());
        $this->assertFalse($home->isPublished(), 'The home page goes back to how the migration left it.');

        $this->assertSame(1, Page::withTrashed()->count(), 'Only the home page is left.');
        $this->assertSame(0, Block::query()->count());
        $this->assertSame(0, $home->versions()->count(), 'The history the demo wrote goes with it.');
        $this->assertSame(0, SeoMeta::query()->count(), 'And so does the card it filled in.');
    }
}
