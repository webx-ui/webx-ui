<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * The three offered block types (§4.8): installed once and published — which means each draws on
 * its own sample — with the kinds of the site as the choice of kinds, and each drawing what the
 * helper gives it.
 */
final class BlockTest extends TestCase
{
    #[Test]
    public function the_three_are_installed_published_and_given_the_kinds_of_the_site(): void
    {
        config()->set('webx-press.kinds', ['interview', 'authored', 'podcast']);

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['press']])->assertSuccessful();

        foreach (['press-logos', 'press-outlets', 'press-articles'] as $slug) {
            $block = Block::query()->where('slug', $slug)->with('publishedVersion')->firstOrFail();

            $this->assertSame('Offered by press', $block->publishedVersion?->comment, "{$slug} is published — it draws on its sample");
        }

        $outlets = Block::query()->where('slug', 'press-outlets')->with('publishedVersion')->firstOrFail();
        $kinds = collect((array) $outlets->publishedVersion?->schema)->firstWhere('id', 'kinds');

        $this->assertSame(
            [['value' => 'interview', 'label' => 'Interview'], ['value' => 'authored', 'label' => 'Authored article'], ['value' => 'podcast', 'label' => 'podcast']],
            $kinds['props']['options'] ?? null,
        );
    }

    #[Test]
    #[DataProvider('samples')]
    public function each_draws_on_its_sample_with_something_to_show_and_without(string $slug, string $expected): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['press']])->assertSuccessful();
        $sample = (array) Block::query()->where('slug', $slug)->with('publishedVersion')->firstOrFail()->publishedVersion?->sample;

        $this->assertStringContainsString('data-wx-block="'.$slug.'"', $this->render($slug, $sample), 'nothing is on the site yet');

        $this->outlet('Tatler', [$this->row('An interview', ['kind' => 'interview', 'published_on' => '2023-08-12'])], values: ['featured' => true]);

        $this->assertStringContainsString($expected, $this->render($slug, $sample));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function samples(): array
    {
        return [
            'logos' => ['press-logos', '<span class="b-press-logos__name">Tatler</span>'],
            'outlets' => ['press-outlets', 'Articles: 1'],
            'articles' => ['press-articles', '<time datetime="2023-08-12">August 12, 2023</time>'],
        ];
    }

    #[Test]
    public function the_logos_are_the_marked_ones_unless_told_otherwise_and_lead_to_the_page(): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['press']])->assertSuccessful();
        $logo = $this->file('media/ab/cd/tatler.png', 'image/png');

        $this->outlet('Tatler', [$this->row('One')], values: ['featured' => true, 'logo' => ['path' => $logo->path]]);
        $this->outlet('Vogue', [$this->row('Two')]);

        $marked = $this->render('press-logos', []);

        $this->assertMatchesRegularExpression('#<a class="b-press-logos__link" href="[^"]*/press/tatler"\s*>#', $marked);
        $this->assertStringContainsString('alt="Tatler"', $marked);
        $this->assertStringNotContainsString('Vogue', $marked, 'an untouched switch is "only the marked ones"');

        $this->assertStringContainsString('Vogue', $this->render('press-logos', ['featured' => false]));
    }

    #[Test]
    public function the_catalogue_groups_by_kind_and_an_outlet_can_be_in_two_groups(): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['press']])->assertSuccessful();

        $this->outlet('Tatler', [$this->row('A', ['kind' => 'authored']), $this->row('B', ['kind' => 'expert_comment'])]);
        $this->outlet('Vogue', [$this->row('C', ['kind' => 'authored'])]);
        $this->outlet('Elle', [$this->row('D', ['kind' => 'mention'])]);

        $flat = $this->render('press-outlets', ['kinds' => []]);
        $this->assertSame(3, substr_count($flat, 'class="b-press-outlets__item"'));
        $this->assertStringNotContainsString('b-press-outlets__heading', $flat);

        $grouped = $this->render('press-outlets', ['group' => true, 'kinds' => ['authored', 'expert_comment']]);

        $this->assertStringContainsString('<h3 class="b-press-outlets__heading">Authored article</h3>', $grouped);
        $this->assertStringContainsString('<h3 class="b-press-outlets__heading">Expert comment</h3>', $grouped);
        $this->assertLessThan(strpos($grouped, 'Expert comment'), strpos($grouped, 'Authored article'), 'the groups in the order chosen');
        $this->assertSame(2, substr_count($grouped, '>Tatler</a>'), 'in both groups');
        $this->assertStringNotContainsString('Elle', $grouped);
    }

    #[Test]
    public function the_feed_is_six_by_default_and_by_date(): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['press']])->assertSuccessful();

        $rows = [];

        foreach (range(1, 8) as $day) {
            $rows[] = $this->row("Article {$day}", ['published_on' => sprintf('2023-08-%02d', $day)]);
        }

        $this->outlet('Tatler', $rows);

        $html = $this->render('press-articles', []);

        $this->assertSame(6, substr_count($html, 'class="b-press-articles__item"'));
        $this->assertLessThan(strpos($html, 'Article 7'), strpos($html, 'Article 8'));
        $this->assertStringNotContainsString('Article 2<', $html);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(string $type, array $values): string
    {
        static $count = 0;
        $count++;

        return (string) $this->app->make(Renderer::class)->render([['key' => "k{$count}", 'type' => $type, 'values' => $values]]);
    }
}
