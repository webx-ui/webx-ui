<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Tariffs\Models\Tariff;

/**
 * The block type the module offers (§4.4): installed once and never over the site's own, drawn in
 * both its layouts on its own sample, "what this service costs" on a service's page, no markup,
 * and a page that goes on answering when the module is gone.
 */
final class BlockTest extends TestCase
{
    #[Test]
    public function the_offered_type_is_installed_and_published(): void
    {
        $block = $this->installBlock()->load('publishedVersion');

        $this->assertSame('Tariffs', $block->title);
        $this->assertSame('Offered by tariffs', $block->publishedVersion?->comment);
        $this->assertSame(
            ['title', 'tariffs', 'layout', 'columns', 'includes_title', 'featured_label'],
            array_column((array) $block->publishedVersion->schema, 'id'),
        );
        $this->assertSame('wx-collection', $block->publishedVersion?->schema[1]['type'] ?? null);
    }

    /**
     * A theme's prose spaces `li + li`, which in a grid or a row lifts the first cell above the
     * others: the cells of the type's lists keep no margin of their own.
     */
    #[Test]
    public function the_cells_of_its_rows_keep_no_margin(): void
    {
        $styles = (string) $this->installBlock()->load('publishedVersion')->publishedVersion?->styles;

        foreach (['.b-tariffs__item', '.b-tariffs__features li', '.b-tariffs__services li'] as $cell) {
            $this->assertMatchesRegularExpression('~^'.preg_quote($cell, '~').'(,\n[^{]*)? \{[^}]*\n    margin: 0[ ;]~m', $styles, $cell);
        }
    }

    #[Test]
    public function a_type_the_site_already_has_by_that_slug_is_never_touched(): void
    {
        $own = Block::query()->create(['slug' => 'tariffs', 'title' => 'Our prices']);
        $own->saveVersion(['template' => '<div data-wx-block="tariffs">Ours</div>']);
        $own->publish();
        $version = $own->refresh()->published_version_id;

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['tariffs']])
            ->expectsOutputToContain('left alone')
            ->assertSuccessful();

        $this->assertSame($version, $own->refresh()->published_version_id);
    }

    /**
     * The offered type is published only if it draws on its own sample — so each layout is drawn
     * on it here, with a tariff without a number and a recommended one among them.
     */
    #[Test]
    #[DataProvider('layouts')]
    public function every_layout_draws_on_the_sample(string $layout, string $expected, bool $nav): void
    {
        $block = $this->installBlock()->load('publishedVersion');
        $sample = (array) $block->publishedVersion?->sample;

        $this->assertStringContainsString('data-tariffs-layout="slider"', $this->render([$this->node($sample)]), 'nothing is on the site yet');

        $this->tariff('Combo Starter', attributes: [
            'badge' => ['en' => '30 HOURS / 25$'],
            'price' => 750,
            'currency' => 'USD',
            'period' => ['en' => '/mo'],
            'features' => [['text' => ['en' => 'Design']], ['text' => ['en' => 'SEO']]],
            'description' => ['en' => "For a start.\n<b>Really</b>."],
            'button_label' => ['en' => 'Get started'],
            'button_link' => self::url('/contacts'),
            'button_variant' => 'secondary',
        ]);
        $this->tariff('Combo Growth', attributes: ['price' => 1380, 'currency' => 'USD', 'featured' => true]);
        $this->tariff('Combo Enterprise', attributes: [
            'price_text' => ['en' => 'On request'],
            'features' => array_map(static fn (int $n): array => ['text' => ['en' => "Line {$n}"]], range(1, 5)),
            'button_label' => ['en' => 'Contact us'],
            'button_link' => self::url('https://example.org/contact', newTab: true),
        ]);

        $html = $this->render([$this->node(['layout' => $layout, 'columns' => 2] + $sample)]);

        $this->assertStringContainsString('data-tariffs-layout="'.$layout.'"', $html);
        $this->assertStringContainsString('style="--tariffs-columns: 2"', $html);
        $this->assertStringContainsString($expected, $html);
        $this->assertSame($nav, str_contains($html, 'b-tariffs__nav'));
        $this->assertStringContainsString('<h2 class="b-tariffs__title">Pricing</h2>', $html);
        $this->assertMatchesRegularExpression('~<article class="b-tariffs__card" id="tariff-\d+">~', $html);
        $this->assertStringContainsString('<p class="b-tariffs__badge">30 HOURS / 25$</p>', $html);
        $this->assertStringContainsString('<span class="b-tariffs__amount">$750</span>', $html);
        $this->assertStringContainsString('<span class="b-tariffs__period">/mo</span>', $html);
        $this->assertStringContainsString('<p class="b-tariffs__includes">This plan includes:</p>', $html);
        $this->assertStringContainsString('<li>Design</li>', $html);
        $this->assertStringContainsString("For a start.<br />\n&lt;b&gt;Really&lt;/b&gt;.", $html);
        $this->assertStringContainsString('<a class="b-tariffs__button b-tariffs__button--secondary" href="http://localhost/contacts">Get started</a>', $html);

        // Recommended: a class and the label over the card.
        $this->assertMatchesRegularExpression('~<article class="b-tariffs__card is-featured" id="tariff-\d+">\s*<p class="b-tariffs__featured">Recommended</p>~', $html);

        // No number: the words, and no symbol.
        $this->assertStringContainsString('<span class="b-tariffs__amount">On request</span>', $html);
        $this->assertStringContainsString('<ul class="b-tariffs__features is-long">', $html, 'more than four lines are two columns');
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer">Contact us</a>', $html);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function layouts(): array
    {
        return [
            'slider' => ['slider', '<div class="b-tariffs__nav" hidden>', true],
            'grid' => ['grid', '<ul class="b-tariffs__track">', false],
        ];
    }

    #[Test]
    public function the_block_holds_its_own_defaults_for_what_the_editor_never_touched(): void
    {
        $this->installBlock();
        $this->tariff('Starter', attributes: ['features' => [['text' => ['en' => 'Design']]], 'featured' => true]);

        $html = $this->render([$this->node()]);

        $this->assertStringContainsString('data-tariffs-layout="slider"', $html, 'an empty layout is the slider');
        $this->assertStringContainsString('style="--tariffs-columns: 3"', $html);
        $this->assertStringContainsString('<div class="b-tariffs__nav" hidden>', $html);
        $this->assertStringContainsString('<span data-tariffs-current>01</span> / <span data-tariffs-total>01</span>', $html);
        $this->assertStringNotContainsString('b-tariffs__includes', $html, 'no heading over the list unless one was written');
        $this->assertStringNotContainsString('b-tariffs__featured', $html, 'the card stands out without a word');
        $this->assertStringContainsString('is-featured', $html);
        $this->assertStringNotContainsString('b-tariffs__price', $html, 'neither a number nor words — no line of price');
    }

    #[Test]
    public function on_a_services_page_it_shows_what_that_service_costs(): void
    {
        $this->installBlock();

        $seo = $this->service('seo');
        $design = $this->service('design');
        $this->tariff('Growth')->syncRelated(Tariff::SERVICES, 'service', [$seo->id]);
        $this->tariff('Starter')->syncRelated(Tariff::SERVICES, 'service', [$design->id]);

        foreach ([$seo, $design] as $service) {
            $service->blocks = [[
                'key' => 'k1',
                'type' => 'tariffs',
                'values' => [
                    'layout' => 'grid',
                    'tariffs' => ['related' => ['type' => 'service', 'ids' => [], 'current' => true]],
                ],
            ]];
            $service->save();
            $service->publish();
        }

        $html = (string) $this->get('/services/seo')->assertOk()->getContent();

        $this->assertStringContainsString('<h3 class="b-tariffs__name">Growth</h3>', $html);
        $this->assertStringNotContainsString('<h3 class="b-tariffs__name">Starter</h3>', $html);
        $this->assertStringContainsString('<a href="http://localhost/services/seo">Seo</a>', $html);
    }

    #[Test]
    public function the_page_carries_no_offer_markup(): void
    {
        $this->installBlock();
        $this->tariff('Starter', attributes: ['price' => 10, 'currency' => 'USD']);

        $this->get($this->pageWith([$this->node()]))
            ->assertOk()
            ->assertSee('Starter', false)
            ->assertDontSee('"Offer"', false)
            ->assertDontSee('"priceCurrency"', false);
    }

    #[Test]
    public function without_the_module_the_block_is_empty_and_the_page_still_answers(): void
    {
        $this->installBlock();
        $this->tariff('Starter');
        $url = $this->pageWith([$this->node()]);

        $this->get($url)->assertOk()->assertSee('<h3 class="b-tariffs__name">Starter</h3>', false);

        $this->app->make(CollectionSources::class)->forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('<ul class="b-tariffs__track">', false)
            ->assertDontSee('<h3 class="b-tariffs__name">Starter</h3>', false);
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

        return ['key' => "k{$count}", 'type' => 'tariffs', 'values' => $values];
    }

    /**
     * A published page with these blocks, at its address.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function pageWith(array $blocks): string
    {
        $page = $this->page('pricing', published: false);
        $page->blocks = $blocks;
        $page->save();
        $page->publish();

        return '/pricing';
    }
}
