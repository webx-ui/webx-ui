<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Pages\Models\Page;
use WebxUi\Seo\Models\SeoMeta;

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
