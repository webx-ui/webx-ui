<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Recipes\RecipesServiceProvider;

/**
 * The index and a category page (§5.4): the catalogue a page at a time, the categories as links,
 * the nutrients as a filter — the filtered copy out of the index, the pages in it.
 */
final class CatalogTest extends TestCase
{
    #[Test]
    public function the_index_lists_the_recipes_a_page_at_a_time(): void
    {
        $this->app['config']->set('webx-recipes.per-page', 2);
        $this->category('breakfasts');

        foreach (['a', 'b', 'c'] as $slug) {
            $this->recipe($slug);
        }

        $this->recipe('draft', published: false);

        $first = (string) $this->get('/recipes')->assertOk()->getContent();

        $this->assertStringContainsString('href="http://localhost/recipes/a"', $first);
        $this->assertStringContainsString('href="http://localhost/recipes/b"', $first);
        $this->assertStringNotContainsString('href="http://localhost/recipes/c"', $first);
        $this->assertStringNotContainsString('recipes/draft', $first);
        $this->assertStringContainsString('href="http://localhost/recipes/breakfasts"', $first);
        $this->assertStringContainsString('href="http://localhost/recipes?page=2" rel="next"', $first);
        $this->assertSame(2, count($this->jsonLd($first, 'ItemList')['itemListElement']));

        $second = (string) $this->get('/recipes?page=2')->assertOk()->getContent();

        $this->assertStringContainsString('href="http://localhost/recipes/c"', $second);
        $this->assertStringNotContainsString('href="http://localhost/recipes/a"', $second);
        // A page of the catalogue is a page of its own to a search engine.
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/recipes?page=2">', $second);
        $this->assertStringNotContainsString('noindex', $second);

        $this->get('/recipes?page=3')->assertNotFound();
    }

    #[Test]
    public function the_filter_narrows_to_a_nutrient_and_keeps_the_copy_out_of_the_index(): void
    {
        $iron = $this->nutrient('Iron');
        $fibre = $this->nutrient('Fibre');
        $hidden = $this->nutrient('Hidden', visible: false);

        $liver = $this->recipe('liver');
        $liver->syncCategories([$iron->id, $hidden->id], 'nutrients');
        $oats = $this->recipe('oats');
        $oats->syncCategories([$fibre->id], 'nutrients');

        $all = (string) $this->get('/recipes')->assertOk()->getContent();

        $this->assertStringContainsString('href="http://localhost/recipes?nutrient='.$iron->id.'"', $all);
        $this->assertStringContainsString('href="http://localhost/recipes?nutrient='.$fibre->id.'"', $all);
        $this->assertStringNotContainsString('Hidden', $all);

        $filtered = (string) $this->get('/recipes?nutrient='.$iron->id)->assertOk()->getContent();

        $this->assertStringContainsString('href="http://localhost/recipes/liver"', $filtered);
        $this->assertStringNotContainsString('href="http://localhost/recipes/oats"', $filtered);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $filtered);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/recipes">', $filtered);
    }

    #[Test]
    public function a_category_page_lists_its_recipes_in_the_order_of_the_whole_list(): void
    {
        $breakfasts = $this->category('breakfasts');
        $this->category('hidden', visible: false);

        $late = $this->recipe('late');
        $early = $this->recipe('early');
        $this->recipe('elsewhere');

        // Filed in the opposite order: the page does not care — recipes have one order.
        $early->syncCategories([$breakfasts->id]);
        $late->syncCategories([$breakfasts->id]);

        $page = (string) $this->get('/recipes/breakfasts')->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Breakfasts</h1>', $page);
        $this->assertLessThan(strpos($page, 'recipes/early"'), strpos($page, 'recipes/late"'));
        $this->assertStringNotContainsString('recipes/elsewhere', $page);
        $this->assertSame(['Home', 'Recipes', 'Breakfasts'], array_column($this->jsonLd($page, 'BreadcrumbList')['itemListElement'], 'name'));

        $this->get('/recipes/hidden')->assertNotFound();
    }

    #[Test]
    public function the_prefix_is_never_empty(): void
    {
        $this->app['config']->set('webx-recipes.prefix', '/');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('webx-recipes.prefix is empty');

        RecipesServiceProvider::prefix($this->app['config']);
    }

    #[Test]
    public function the_index_is_the_packages_while_it_is_on(): void
    {
        $this->assertTrue($this->app['router']->has(RecipesServiceProvider::INDEX_ROUTE));
    }
}
