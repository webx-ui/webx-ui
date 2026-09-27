<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use PHPUnit\Framework\Attributes\Test;
use ReflectionFunction;
use WebxUi\Press\Support\Kinds;

/**
 * `press()` (§4.8): every step of the table, the feed by date with the undated last, `take()`
 * counted after what is not seen, and `kind()` of outlets as "ran an article of this kind".
 */
final class HelperTest extends TestCase
{
    #[Test]
    public function outlets_come_in_their_own_order_and_only_the_seen_ones(): void
    {
        $this->outlet('First', [$this->row('A')]);
        $this->outlet('Draft', [$this->row('B')], published: false);
        $this->outlet('Russian only', [['title' => ['ru' => 'Только'], 'url' => 'https://x.example']]);
        $this->outlet('Empty');
        $this->outlet('Second', [$this->row('C')]);

        $this->assertSame(['First', 'Second'], array_column(press()->get(), 'title'));
        $this->assertSame(['First', 'Second'], array_column(press()->outlets()->get(), 'title'));
        $this->assertSame(['Russian only'], array_column(press()->locale('ru')->get(), 'title'), 'the name from the language there is');
    }

    #[Test]
    public function featured_kind_only_except_and_take(): void
    {
        $a = $this->outlet('A', [$this->row('A1', ['kind' => 'interview'])], values: ['featured' => true]);
        $b = $this->outlet('B', [$this->row('B1', ['kind' => 'authored']), $this->row('B2', ['kind' => 'interview', 'is_hidden' => true])]);
        $c = $this->outlet('C', [$this->row('C1', ['kind' => 'authored'])], values: ['featured' => true]);

        $this->assertSame(['A', 'C'], array_column(press()->featured()->get(), 'title'));
        $this->assertSame(['A'], array_column(press()->kind('interview')->get(), 'title'), 'a hidden interview is no interview');
        $this->assertSame(['B', 'C'], array_column(press()->kind(['authored'])->get(), 'title'));
        $this->assertSame(['A', 'B', 'C'], array_column(press()->kind(null)->get(), 'title'), 'no kind is every kind');
        $this->assertSame(['C', 'A'], array_column(press()->only([$c->id, $a])->get(), 'title'));
        $this->assertSame(['A', 'C'], array_column(press()->except($b)->get(), 'title'));
        $this->assertSame(['A', 'B'], array_column(press()->take(2)->get(), 'title'));
        $this->assertSame('A', press()->first()['title'] ?? null);
        $this->assertCount(3, press());
    }

    #[Test]
    public function take_counts_what_is_seen(): void
    {
        $this->outlet('Hidden', [$this->row('H', ['is_hidden' => true])]);
        $this->outlet('A', [$this->row('A1')]);
        $this->outlet('B', [$this->row('B1')]);

        $this->assertSame(['A'], array_column(press()->take(1)->get(), 'title'));
    }

    #[Test]
    public function the_feed_goes_by_date_the_undated_last(): void
    {
        $this->outlet('Tatler', [
            $this->row('Undated'),
            $this->row('Old', ['published_on' => '2020-05-01']),
            $this->row('Hidden', ['published_on' => '2025-01-01', 'is_hidden' => true]),
        ]);
        $this->outlet('Vogue', [
            $this->row('New', ['published_on' => '2024-02-01', 'kind' => 'interview']),
            $this->row('English only', ['published_on' => '2023-01-01']),
            $this->row('Both', ['published_on' => '2022-01-01', 'title' => ['en' => 'Both', 'ru' => 'Обе']]),
        ]);
        $this->outlet('Draft', [$this->row('Draft article', ['published_on' => '2026-01-01'])], published: false);

        $this->assertSame(['New', 'English only', 'Both', 'Old', 'Undated'], array_column(press()->articles()->get(), 'title'));
        $this->assertSame(['New', 'English only'], array_column(press()->articles()->take(2)->get(), 'title'));
        $this->assertSame(['New'], array_column(press()->articles()->kind('interview')->get(), 'title'));
        $this->assertSame(['Обе'], array_column(press()->articles()->locale('ru')->get(), 'title'));
    }

    #[Test]
    public function the_steps_carry_over_from_outlets_to_articles(): void
    {
        $this->outlet('Vogue', [
            $this->row('V1', ['published_on' => '2024-01-01']),
            $this->row('V2', ['published_on' => '2023-01-01']),
        ], values: ['featured' => true]);
        $this->outlet('Tatler', [$this->row('T1', ['published_on' => '2025-01-01'])]);

        $feed = press()->featured()->articles();
        $ids = $feed->models()->modelKeys();

        $this->assertSame(['V1', 'V2'], array_column($feed->get(), 'title'), 'featured narrows the feed to the outlets of the strip');
        $this->assertSame(['V2', 'V1'], array_column($feed->only(array_reverse($ids))->get(), 'title'));
        $this->assertSame(['V2'], array_column($feed->except($ids[0])->get(), 'title'));
        $this->assertCount(2, $feed);
        $this->assertFalse($feed->isEmpty());
        $this->assertTrue($feed->locale('ru')->isEmpty());
        $this->assertSame(['Vogue', 'Tatler'], array_column($feed->outlets()->featured(false)->get(), 'title'), 'back to outlets, the other steps kept');
    }

    #[Test]
    public function the_cards_carry_what_the_table_says(): void
    {
        $logo = $this->file('media/ab/cd/tatler.png', 'image/png');
        $scan = $this->file();

        $outlet = $this->outlet('Tatler', [
            $this->row('Interview', ['kind' => 'interview', 'published_on' => '2023-08-12', 'date_precision' => 'month', 'excerpt' => ['en' => 'About.'], 'file' => ['path' => $scan->path]]),
            $this->row('Mention', ['kind' => 'mention']),
        ], values: ['logo' => ['path' => $logo->path], 'summary' => ['en' => 'A magazine.'], 'website_url' => 'https://tatler.example', 'featured' => true]);

        $card = press()->first();
        $this->assertIsArray($card);
        $this->assertSame([
            'id', 'url', 'link', 'title', 'summary', 'logo', 'website', 'featured', 'count', 'kinds', 'fields',
        ], array_keys($card));
        $this->assertSame($outlet->id, $card['id']);
        $this->assertSame(url('press/tatler'), $card['url']);
        $this->assertSame($card['url'], $card['link']);
        $this->assertSame('A magazine.', $card['summary']);
        $this->assertStringContainsString('tatler.png', (string) $card['logo']['url']);
        $this->assertSame('https://tatler.example', $card['website']);
        $this->assertTrue($card['featured']);
        $this->assertSame(2, $card['count']);
        $this->assertSame(['mention', 'interview'], $card['kinds'], 'in the order of the config');

        $article = press()->articles()->kind('interview')->first();
        $this->assertIsArray($article);
        $this->assertSame([
            'id', 'outlet', 'title', 'excerpt', 'kind', 'kind_label', 'date', 'when', 'target', 'url', 'pdf', 'fields',
        ], array_keys($article));
        $this->assertSame(['id' => $outlet->id, 'title' => 'Tatler', 'url' => url('press/tatler')], array_diff_key($article['outlet'], ['logo' => 1]));
        $this->assertStringContainsString('tatler.png', (string) $article['outlet']['logo']);
        $this->assertSame('Interview', $article['kind_label']);
        $this->assertSame('2023-08-12', $article['date']);
        $this->assertSame('August 2023', $article['when']);
        $this->assertSame('https://news.example/interview', $article['target']);
        $this->assertSame($article['target'], $article['url']);
        $this->assertStringContainsString('/scan.pdf', (string) $article['pdf']);
        $this->assertSame('About.', $article['excerpt']);

        $this->assertSame([], press()->articles()->locale('ru')->get(), 'no Russian title, no card');
        $this->assertSame('Интервью', Kinds::label('interview', 'ru'));
    }

    #[Test]
    public function the_helper_is_ours(): void
    {
        $this->assertSame(
            realpath(__DIR__.'/../src/helpers.php'),
            realpath((string) (new ReflectionFunction('press'))->getFileName()),
        );
    }
}
