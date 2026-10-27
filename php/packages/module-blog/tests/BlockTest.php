<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blog\Collections\ArticlesSource;
use WebxUi\Blog\Rendering\ArticleQuery;
use WebxUi\Cache\CacheGroups;
use WebxUi\Routing\RouteCache;

/**
 * The articles a block shows (§20): the top of the feed, narrowed by rubric, and the block the
 * module offers, which prints them as the feed's own cards.
 */
final class BlockTest extends TestCase
{
    #[Test]
    public function the_source_is_the_top_of_the_feed_and_only_what_a_reader_may_see(): void
    {
        $this->article('older', at: Carbon::now()->subDays(3));
        $newest = $this->article('newest', at: Carbon::now()->subDay());
        $pinned = $this->article('pinned', at: Carbon::now()->subWeek());
        $pinned->forceFill(['pinned' => true])->save();
        $this->article('draft', published: false);
        $this->article('next-week', at: Carbon::now()->addWeek());

        $items = $this->source()->items(Selection::of(['limit' => 2], $this->source()), 'en');

        $this->assertSame(['Pinned', 'Newest'], array_column($items, 'title'));
        $this->assertSame($newest->url('en'), $items[1]['url']);
        $this->assertSame('newest', $items[1]['anchor']);
    }

    #[Test]
    public function a_rubric_narrows_it_and_a_card_names_its_rubric_and_tags(): void
    {
        $repairs = $this->rubric('repairs');
        $belt = $this->article('changing-a-belt');
        $belt->rubrics()->attach($repairs);
        $belt->tags()->attach($this->tag('belts'));
        $this->article('somebody-else');

        $items = $this->source()->items(Selection::of(['categories' => [$repairs->getKey()]], $this->source()), 'en');

        $this->assertSame(['Changing a belt'], array_column($items, 'title'));
        $this->assertSame([$repairs->getKey()], $items[0]['categories']);
        $this->assertSame('Repairs', $items[0]['rubric']['title']);
        $this->assertSame(['Belts'], array_column($items[0]['tags'], 'title'));
    }

    #[Test]
    public function the_offered_block_prints_the_feeds_cards_and_a_link_to_the_feed(): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['blog']])->assertSuccessful();
        $this->assertSame('Latest articles', Block::query()->where('slug', 'blog')->firstOrFail()->title);

        $this->article('older', at: Carbon::now()->subDays(3));
        $this->article('newest', at: Carbon::now()->subDay());
        $this->article('the-third', at: Carbon::now()->subDays(5));

        $html = (string) $this->app->make(Renderer::class)->render([
            ['key' => 'k1', 'type' => 'blog', 'values' => ['title' => ['en' => 'From the blog'], 'articles' => ['limit' => 2]]],
        ]);

        $this->assertStringContainsString('<h2 class="b-blog__title">From the blog</h2>', $html);
        $this->assertStringContainsString('href="'.url('/blog').'">All articles</a>', $html);
        $this->assertStringContainsString('<ul class="webx-blog-cards">', $html);
        $this->assertLessThan(strpos($html, 'Older'), strpos($html, 'Newest'));
        $this->assertStringNotContainsString('The third', $html);
    }

    #[Test]
    public function the_block_hands_the_cards_partial_models_in_the_order_it_was_given(): void
    {
        $first = $this->article('first', at: Carbon::now()->subDays(2));
        $second = $this->article('second', at: Carbon::now()->subDay());

        $models = ArticleQuery::block(['items' => [['id' => $first->getKey()], ['id' => $second->getKey()]]]);

        $this->assertSame([$first->getKey(), $second->getKey()], $models->modelKeys());
        $this->assertCount(0, ArticleQuery::block(null));
    }

    #[Test]
    public function the_block_reads_each_kind_of_address_once_and_its_articles_once(): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['blog']])->assertSuccessful();

        $belts = $this->tag('belts');
        $filters = $this->tag('filters');

        foreach (['first', 'second', 'third'] as $index => $slug) {
            $this->article($slug, at: Carbon::now()->subDays($index + 1))->tags()->attach([$belts->getKey(), $filters->getKey()]);
        }

        // The first visitor: nothing in the store, nothing in this process.
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
        $this->app->forgetInstance(RouteCache::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $html = (string) $this->app->make(Renderer::class)->render([
            ['key' => 'k1', 'type' => 'blog', 'values' => ['articles' => ['limit' => 3]]],
        ]);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $routes = array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'from "routes"'));
        $articles = array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'from "blog_articles"'));

        // The articles and their tags: one query for both types, the shared tags read once.
        $this->assertCount(1, $routes, implode("\n", $routes));
        $this->assertCount(1, $articles, implode("\n", $articles));
        $this->assertStringContainsString(url('/blog/second'), $html);
    }

    private function source(): ArticlesSource
    {
        $source = $this->app->make(CollectionSources::class)->find(ArticlesSource::KEY);
        $this->assertInstanceOf(ArticlesSource::class, $source);

        return $source;
    }
}
