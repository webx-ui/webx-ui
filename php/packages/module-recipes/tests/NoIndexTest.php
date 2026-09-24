<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Models\Page;
use WebxUi\Recipes\RecipesServiceProvider;

/**
 * `webx-recipes.index` off (decision 14): the prefix stays, the route under it goes, and the
 * address is free for a page of `module-pages` — which gets it, and which the trail of a recipe
 * then starts with (CLAUDE.md §4 on `Reserved`).
 */
final class NoIndexTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-recipes.index', false);
    }

    #[Test]
    public function the_route_is_gone_and_a_page_takes_the_address_and_the_first_step_of_the_trail(): void
    {
        $this->assertFalse($this->app['router']->has(RecipesServiceProvider::INDEX_ROUTE));

        $breakfasts = $this->category('breakfasts');
        $recipe = $this->recipe('porridge');
        $recipe->syncCategories([$breakfasts->id]);

        // Nothing at the prefix yet: no step rather than one that leads to a 404.
        $this->assertSame(['Home', 'Breakfasts', 'Porridge'], $this->names('/recipes/porridge'));

        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $page = new Page(['title' => 'Our recipes', 'slug' => 'recipes']);
        $page->appendTo($home);
        $page->publish();

        $this->assertSame('recipes', $page->refresh()->routeCanonical()?->path);
        $this->assertSame(['Home', 'Our recipes', 'Breakfasts', 'Porridge'], $this->names('/recipes/porridge'));
        $this->assertSame(['Home', 'Our recipes', 'Breakfasts'], $this->names('/recipes/breakfasts'));
    }

    /** @return list<string> */
    private function names(string $url): array
    {
        $page = (string) $this->get($url)->assertOk()->getContent();

        return array_column($this->jsonLd($page, 'BreadcrumbList')['itemListElement'], 'name');
    }
}
