<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Models\Block;

/**
 * The block types a module offers (§3.4 of the FAQ spec): listed, installed once, and never laid
 * over a type the site already has by that name.
 */
final class OffersTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/offers-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->dir);

        $this->write('faq', '<section data-wx-block="faq">{{ $title }}</section>');
        $this->write('faq-teaser', '<aside data-wx-block="faq-teaser">{{ $title }}</aside>');

        $offers = $this->app->make(BlockOffers::class);
        $offers->offer('faq', $this->dir);
        $offers->offer('team', __DIR__.'/nowhere.json');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function it_lists_what_is_offered_and_installs_what_is_missing(): void
    {
        $this->artisan('webx:blocks:offered')
            ->expectsOutputToContain('not installed')
            ->assertSuccessful();

        $this->assertSame(0, Block::query()->count(), 'listing writes nothing');

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['faq']])
            ->expectsOutputToContain('installed and published')
            ->assertSuccessful();

        $faq = Block::query()->where('slug', 'faq')->with('publishedVersion')->firstOrFail();

        $this->assertSame('Faq', $faq->title);
        $this->assertSame('Offered by faq', $faq->publishedVersion?->comment);
        $this->assertSame(2, Block::query()->count());
    }

    #[Test]
    public function a_type_by_the_same_slug_is_never_touched(): void
    {
        $own = $this->publish('faq', '<div data-wx-block="faq">Ours</div>');
        $version = $own->published_version_id;

        $this->artisan('webx:blocks:offered', ['--install' => true])
            ->expectsOutputToContain('left alone')
            ->assertSuccessful();

        $own->refresh();

        $this->assertStringContainsString('Ours', (string) $own->publishedVersion?->template);
        $this->assertSame($version, $own->published_version_id);
        $this->assertNull($own->draft_version_id);
        $this->assertTrue(Block::query()->where('slug', 'faq-teaser')->exists(), 'what was missing is installed all the same');
    }

    #[Test]
    public function other_modules_are_left_out_when_some_are_named(): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['team']])
            ->expectsOutputToContain('offer no block types')
            ->assertSuccessful();

        $this->assertSame(0, Block::query()->count());
    }

    private function write(string $slug, string $template): void
    {
        File::put("{$this->dir}/{$slug}.json", (string) json_encode([
            'slug' => $slug,
            'title' => ucfirst($slug),
            'schema' => [['id' => 'title', 'type' => 'wx-input']],
            'template' => $template,
            'sample' => ['title' => 'Questions'],
        ]));
    }
}
