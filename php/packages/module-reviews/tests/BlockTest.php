<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Pages\Models\Page;

/**
 * The block type the module offers (§4.5): installed once and never over the site's own, drawn in
 * each of its four layouts, and a page that goes on answering when the module is gone.
 */
final class BlockTest extends TestCase
{
    #[Test]
    public function the_offered_type_is_installed_and_published(): void
    {
        $block = $this->installBlock()->load('publishedVersion');

        $this->assertSame('Reviews', $block->title);
        $this->assertSame('Offered by reviews', $block->publishedVersion?->comment);
        $this->assertSame(
            ['title', 'reviews', 'layout', 'columns', 'autoplay', 'speed', 'all_label'],
            array_column((array) $block->publishedVersion->schema, 'id'),
        );
        $this->assertSame('wx-collection', $block->publishedVersion?->schema[1]['type'] ?? null);
    }

    #[Test]
    public function a_type_the_site_already_has_by_that_slug_is_never_touched(): void
    {
        $own = Block::query()->create(['slug' => 'reviews', 'title' => 'Testimonials']);
        $own->saveVersion(['template' => '<div data-wx-block="reviews">Ours</div>']);
        $own->publish();
        $version = $own->refresh()->published_version_id;

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['reviews']])
            ->expectsOutputToContain('left alone')
            ->assertSuccessful();

        $own->refresh();

        $this->assertSame('Testimonials', $own->title);
        $this->assertSame($version, $own->published_version_id);
    }

    /**
     * The offered type is published only if it draws on its own sample — so each layout is drawn
     * on it here, the way `webx:blocks:offered` would, with a review to show and without.
     */
    #[Test]
    #[DataProvider('layouts')]
    public function every_layout_draws_on_the_sample(string $layout, string $expected): void
    {
        $block = $this->installBlock()->load('publishedVersion');
        $sample = (array) $block->publishedVersion?->sample;

        $this->assertStringContainsString('data-reviews-layout="grid"', $this->render([$this->node($sample)]), 'nothing is on the site yet');

        $this->review('Anna Petrova', attributes: ['rating' => 4, 'job_title' => ['en' => 'CEO'], 'reviewed_on' => '2026-09-20']);
        $this->review('Boris');

        $html = $this->render([$this->node(['layout' => $layout, 'columns' => 2, 'autoplay' => true, 'speed' => 60] + $sample)]);

        $this->assertStringContainsString('data-reviews-layout="'.$layout.'"', $html);
        $this->assertStringContainsString($expected, $html);
        $this->assertStringContainsString('<blockquote class="b-reviews__text">Anna Petrova liked it.</blockquote>', $html);
        $this->assertStringContainsString('<span class="b-reviews__initials" aria-hidden="true">AP</span>', $html);
        $this->assertStringContainsString('style="--rating: 4" role="img" aria-label="4 / 5"', $html);
        $this->assertStringContainsString('<time class="b-reviews__date" datetime="2026-09-20">', $html);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function layouts(): array
    {
        return [
            'one' => ['single', 'class="b-reviews__track"'],
            'grid' => ['grid', 'style="--reviews-columns: 2"'],
            'slider' => ['slider', 'data-reviews-autoplay="on"'],
            'marquee' => ['marquee', 'data-reviews-speed="60"'],
        ];
    }

    #[Test]
    public function the_block_holds_its_own_defaults_for_what_the_editor_never_touched(): void
    {
        $this->installBlock();
        $this->review('Anna');

        $html = $this->render([$this->node()]);

        $this->assertStringContainsString('data-reviews-layout="grid"', $html);
        $this->assertStringContainsString('style="--reviews-columns: 3"', $html);
        $this->assertStringContainsString('data-reviews-speed="40"', $html);
        $this->assertStringContainsString('data-reviews-autoplay="off"', $html);
        $this->assertStringContainsString('Anna liked it.', $html, 'an untouched collection is every review');
        $this->assertStringNotContainsString('b-reviews__nav', $html, 'the arrows are the slider\'s');
    }

    #[Test]
    public function one_shows_the_first_and_a_slider_has_its_arrows(): void
    {
        $this->installBlock();
        $this->review('Anna');
        $this->review('Boris');

        $single = $this->render([$this->node(['layout' => 'single'])]);
        $this->assertStringContainsString('Anna liked it.', $single);
        $this->assertStringNotContainsString('Boris liked it.', $single);

        $slider = $this->render([$this->node(['layout' => 'slider'])]);
        $this->assertStringContainsString('Boris liked it.', $slider);
        $this->assertStringContainsString('<div class="b-reviews__nav" hidden>', $slider);
    }

    #[Test]
    public function the_text_keeps_its_lines_and_a_profile_is_a_link_nobody_follows(): void
    {
        $this->installBlock();
        $this->review('Anna', attributes: [
            'text' => ['en' => "First line.\n<b>Second</b> line."],
            'profile_url' => 'https://example.com/anna',
        ]);

        $html = $this->render([$this->node()]);

        $this->assertStringContainsString("First line.<br />\n&lt;b&gt;Second&lt;/b&gt; line.", $html);
        $this->assertStringContainsString('<a class="b-reviews__name" href="https://example.com/anna" rel="nofollow noopener" target="_blank">Anna</a>', $html);
    }

    #[Test]
    public function the_filter_has_a_button_for_every_category_that_shows_something(): void
    {
        $this->installBlock();
        $clinic = $this->category('Clinic');
        $this->category('Empty');
        $this->review('Anna', categories: [$clinic]);

        $html = $this->render([$this->node(['reviews' => ['filter' => true], 'all_label' => ['en' => 'Everyone']])]);

        $this->assertStringContainsString('data-reviews-group="'.$clinic->id.'"', $html);
        $this->assertStringContainsString('data-reviews-categories="'.$clinic->id.'"', $html);
        $this->assertStringContainsString('>Everyone</button>', $html);
        $this->assertStringNotContainsString('>Empty</button>', $html);

        $this->assertStringNotContainsString('b-reviews__filter', $this->render([$this->node(['reviews' => ['filter' => true], 'layout' => 'single'])]));
    }

    #[Test]
    public function the_page_carries_no_review_markup(): void
    {
        $this->installBlock();
        $this->review('Anna', attributes: ['rating' => 5]);

        $this->get($this->page([$this->node()]))
            ->assertOk()
            ->assertSee('Anna liked it.', false)
            ->assertDontSee('"Review"', false)
            ->assertDontSee('AggregateRating', false);
    }

    #[Test]
    public function without_the_module_the_block_is_empty_and_the_page_still_answers(): void
    {
        $this->installBlock();
        $this->review('Anna');
        $url = $this->page([$this->node()]);

        $this->get($url)->assertOk()->assertSee('Anna liked it.', false);

        $this->app->make(CollectionSources::class)->forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('<ul class="b-reviews__track">', false)
            ->assertDontSee('Anna liked it.', false);
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     */
    private function render(array $blocks): string
    {
        return (string) $this->app->make(Renderer::class)->render($blocks);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function node(array $values = []): array
    {
        static $count = 0;
        $count++;

        return ['key' => "k{$count}", 'type' => 'reviews', 'values' => $values];
    }

    /**
     * A published page with these blocks, at its address.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(array $blocks): string
    {
        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $page = new Page(['title' => 'Reviews', 'slug' => 'reviews']);
        $page->appendTo($home);
        $page->blocks = $blocks;
        $page->save();
        $page->publish();

        return '/reviews';
    }
}
