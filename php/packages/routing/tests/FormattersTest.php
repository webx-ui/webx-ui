<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Formatters\PathFormatter;
use WebxUi\Routing\Formatters\Prefixed;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\Formatters\SlugId;
use WebxUi\Routing\Formatters\SlugSku;
use WebxUi\Routing\Formatters\TreePath;
use WebxUi\Routing\Tests\Fixtures\Article;
use WebxUi\Routing\Tests\Fixtures\Category;
use WebxUi\Routing\Tests\Fixtures\Product;

class FormattersTest extends TestCase
{
    #[Test]
    public function slug_is_the_slug_alone(): void
    {
        $category = Category::query()->create(['name' => 'Parts', 'slug' => 'Parts']);

        $this->assertSame('parts', (new Slug)->format($category, 'en'));
    }

    #[Test]
    public function tree_path_walks_the_ancestors(): void
    {
        $about = $this->page('about');
        $mission = $this->page('mission', $about);

        $this->assertSame('about', (new TreePath)->format($about, 'en'));
        $this->assertSame('about/mission', (new TreePath)->format($mission, 'en'));
    }

    #[Test]
    public function tree_path_follows_the_language(): void
    {
        $this->useLocales(['en', 'uk']);

        $about = $this->page('about');
        $about->setTranslation('slug', 'uk', 'pro-nas')->saveQuietly();

        $mission = $this->page('mission', $about);
        $mission->setTranslation('slug', 'uk', 'misiya')->saveQuietly();

        $this->assertSame('about/mission', (new TreePath)->format($mission->refresh(), 'en'));
        $this->assertSame('pro-nas/misiya', (new TreePath)->format($mission->refresh(), 'uk'));
    }

    #[Test]
    public function slug_id_puts_the_key_on_the_end(): void
    {
        $article = Article::query()->create(['title' => 'How to choose a belt', 'slug' => 'kak-vybrat-remen']);

        $this->assertSame('kak-vybrat-remen-'.$article->getKey(), (new SlugId)->format($article, 'en'));
    }

    #[Test]
    public function slug_sku_puts_the_article_number_on_the_end(): void
    {
        $product = Product::query()->create([
            'name' => 'Hydraulic oil filter',
            'slug' => 'hydraulic-oil-filter',
            'sku' => '46969598',
        ]);

        $this->assertSame('hydraulic-oil-filter-46969598', (new SlugSku)->format($product, 'en'));
    }

    #[Test]
    public function slug_sku_falls_back_to_the_bare_slug(): void
    {
        $product = new Product(['name' => 'Belt', 'slug' => 'belt', 'sku' => null]);

        $this->assertSame('belt', (new SlugSku)->format($product, 'en'));
    }

    #[Test]
    public function prefixed_puts_another_formatter_under_a_segment(): void
    {
        $article = Article::query()->create(['title' => 'How to choose a belt', 'slug' => 'kak-vybrat-remen']);

        $formatter = new Prefixed('article', Slug::class);

        $this->assertSame('article/kak-vybrat-remen', $formatter->format($article, 'en'));
    }

    #[Test]
    public function a_project_can_write_its_own_formatter(): void
    {
        $formatter = new class implements PathFormatter
        {
            public function format(Model $entity, string $locale): string
            {
                return 'shop/'.$entity->getAttribute('sku');
            }
        };

        $this->app['config']->set('webx-routing.types.product.formatter', $formatter);

        $product = Product::query()->create(['name' => 'Belt', 'slug' => 'belt', 'sku' => '7100104']);

        $this->assertSame('shop/7100104', $product->routePath());
    }

    #[Test]
    public function a_page_without_a_tree_says_so(): void
    {
        $category = Category::query()->create(['name' => 'Parts', 'slug' => 'parts']);

        $this->expectExceptionMessageMatches('/is not a tree/');

        (new TreePath)->format($category, 'en');
    }
}
