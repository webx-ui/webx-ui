<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Seo\Models\SeoUrl;

/**
 * The demo content of the blog, and the two things about it that only show up here.
 *
 * The cover comes out of the journal of the run that is still going — that is what `requires`
 * buys, and the reason the library is seeded before the blog although the panel lists it
 * after. And one of the two articles is dated ahead of today, because «Scheduled» is a state
 * a new site should be able to see rather than read about.
 */
final class DemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_a_rubric_two_tags_and_two_articles_one_of_them_scheduled(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(1, Rubric::query()->count());
        $this->assertSame(2, Tag::query()->count());
        $this->assertSame(2, Article::query()->count());

        $live = Article::query()->published()->get();

        $this->assertCount(1, $live, 'One of the two is dated ahead of today.');
        $this->assertTrue(Article::query()->where('slug->en', 'what-comes-next-week')->firstOrFail()->isScheduled());
    }

    #[Test]
    public function the_cover_is_the_picture_the_library_seeded_a_moment_earlier(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $article = Article::query()->where('slug->en', 'a-site-made-of-blocks')->firstOrFail();
        $cover = $article->cover;

        $this->assertInstanceOf(MediaFile::class, $cover);
        $this->assertSame(1600, $cover->width);
        Storage::disk('public')->assertExists($cover->path);
    }

    #[Test]
    public function the_seo_rule_is_a_mask_over_the_addresses_of_the_blog(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $rule = SeoUrl::query()->firstOrFail();

        $this->assertSame('mask', $rule->match_type);
        $this->assertSame('/blog/*', $rule->pattern);
    }

    #[Test]
    public function removing_takes_the_bytes_with_the_rows(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $path = MediaFile::query()->firstOrFail()->path;

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Article::withTrashed()->count());
        $this->assertSame(0, Rubric::withTrashed()->count());
        $this->assertSame(0, Tag::query()->count());
        $this->assertSame(0, MediaFile::query()->count());
        $this->assertSame(0, SeoUrl::query()->count());

        Storage::disk('public')->assertMissing($path);
    }
}
