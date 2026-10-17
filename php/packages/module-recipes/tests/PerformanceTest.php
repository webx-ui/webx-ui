<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Cache\CacheGroups;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeNutrient;

/**
 * The query budget of every public page of the recipes, and the freshness of what they cache
 * (WEBX_UI_PERFORMANCE_AUDIT.md §7).
 *
 * Every budget is a ceiling the page fits under today with thirty recipes, not a target, and the
 * point of each is that it does not grow with the rows: a page that asks the registry, the
 * library or the relations once per card is caught by `assertNoRepeatedQueries()` on the warm
 * pass whatever the count. A change that needs more says why here before raising the number.
 *
 * The recipe page is not held to "no repeats": its services and the services of the similar
 * recipes under it are two lists, read one after the other, and the relations of each are read
 * once for the whole list — the same shape twice, never once per card.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    /**
     * The panel's list, its form and the agent's list (WEBX_UI_PERFORMANCE_AUDIT.md §9.5): each
     * row hashes its revision out of what the page loaded at once, so a list costs the same for
     * five rows and for fifty. Warm — the request before it filled the caches every panel request
     * reads — because what is measured is the endpoint, not the first visitor after a deploy.
     */
    #[Test]
    public function the_panel_fits_its_budgets_and_does_not_grow_with_the_rows(): void
    {
        $this->seedRecipes();
        $admin = CmsUser::query()->create(['name' => 'Admin', 'email' => 'budget@example.test', 'password' => 'correct-horse-battery', 'is_super' => true, 'is_active' => true]);
        $id = (string) Recipe::query()->orderBy('id')->value('id');

        // [address, budget]; nothing may repeat on any of them
        foreach ([
            ['/api/cms/recipes', 12],
            ['/api/cms/recipes/{id}', 9],
        ] as [$url, $budget]) {
            $url = str_replace('{id}', $id, $url);
            $this->actingAs($admin, 'cms')->getJson($url)->assertOk();

            $this->assertQueriesWithin($budget, fn () => $this->actingAs($admin, 'cms')->getJson($url)->assertOk(), $url);
            $this->assertNoRepeatedQueries(fn () => $this->actingAs($admin, 'cms')->getJson($url)->assertOk(), $url);
        }

        foreach ([
            ['recipes_list', 6],
        ] as [$tool, $budget]) {
            $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
            $this->assertQueriesWithin($budget, fn () => WebxServer::actingAs($admin, 'cms')->tool($bound, [])->assertOk(), $tool);
            $this->assertNoRepeatedQueries(fn () => WebxServer::actingAs($admin, 'cms')->tool($bound, [])->assertOk(), $tool);
        }
    }

    private const RECIPES = 30;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them: the budget of a warm page is the point.
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
    }

    #[Test]
    public function the_index_fits_its_budget_cold_and_warm(): void
    {
        $this->seedRecipes();

        $this->cold();
        $this->assertQueriesWithin(18, fn () => $this->get('/recipes')->assertOk());

        $this->assertQueriesWithin(13, fn () => $this->get('/recipes')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/recipes')->assertOk());
    }

    #[Test]
    public function a_filtered_index_fits_its_budget_cold_and_warm(): void
    {
        $this->seedRecipes();
        $iron = RecipeNutrient::query()->where('title->en', 'Iron')->firstOrFail();
        $path = '/recipes?nutrient='.$iron->getKey();

        $this->cold();
        $this->assertQueriesWithin(18, fn () => $this->get($path)->assertOk());

        $this->assertQueriesWithin(13, fn () => $this->get($path)->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get($path)->assertOk());
    }

    #[Test]
    public function a_category_fits_its_budget_cold_and_warm(): void
    {
        $this->seedRecipes();

        $this->cold();
        $this->assertQueriesWithin(20, fn () => $this->get('/recipes/breakfasts')->assertOk());

        $this->assertQueriesWithin(14, fn () => $this->get('/recipes/breakfasts')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/recipes/breakfasts')->assertOk());
    }

    #[Test]
    public function a_recipe_fits_its_budget_cold_and_warm_with_chosen_and_with_picked_similar_ones(): void
    {
        $this->seedRecipes();

        // Chosen by hand: the role `related` names them, and they come through the picker's query.
        $this->cold();
        $this->assertQueriesWithin(30, fn () => $this->get('/recipes/recipe-2')->assertOk());
        $this->assertQueriesWithin(24, fn () => $this->get('/recipes/recipe-2')->assertOk());

        // Picked by what they share: one query over the candidates, loaded for the few shown.
        $this->cold();
        $this->assertQueriesWithin(30, fn () => $this->get('/recipes/recipe-20')->assertOk());
        $this->assertQueriesWithin(24, fn () => $this->get('/recipes/recipe-20')->assertOk());
    }

    #[Test]
    public function the_sitemap_fits_its_budget(): void
    {
        $this->seedRecipes();

        // The build: the recipes and the categories, chunked, with their cards and the covers
        // behind `og:image` in bulk; the date is a column.
        $this->cold();
        $this->assertQueriesWithin(18, fn () => $this->get('/sitemap.xml')->assertOk());
        // Served from the cache: nothing but the request's own infrastructure.
        $this->assertQueriesWithin(2, fn () => $this->get('/sitemap-recipe.xml')->assertOk());
    }

    /**
     * The registry and the shared data of a page are cached on versioned keys; a save through
     * the panel moves their groups, and the next request — in the same process, the way a worker
     * serves it — reads the new rows. The first recipe is the one on the first page.
     */
    #[Test]
    public function a_page_shows_a_change_made_through_the_panel_at_once(): void
    {
        $this->seedRecipes();
        $editor = $this->editor();

        $this->get('/recipes')->assertOk()->assertSee('Recipe 1');
        $this->get('/recipes/recipe-1')->assertOk()->assertSee('Recipe 1');

        $recipe = Recipe::query()->where('slug->en', 'recipe-1')->firstOrFail();
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($recipe->getKey()), ['values' => ['title' => ['en' => 'The first, renamed'], 'slug' => ['en' => 'the-first']]])
            ->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->api($recipe->getKey().'/publish'))->assertOk();

        $this->get('/recipes')->assertOk()->assertSee('The first, renamed')->assertDontSee('>Recipe 1<', false);
        $this->get('/recipes/the-first')->assertOk()->assertSee('The first, renamed');
        $this->get('/recipes/recipe-1')->assertRedirect('/recipes/the-first');
    }

    /**
     * Three categories, four nutrients, four pictures, two services and thirty recipes: each with
     * a gallery of two (the cover differs from card to card, so a lookup per cover would show), a
     * category, two nutrients, the two documents and a nutrition table; every second one related
     * to the services; the first half with similar recipes chosen by hand, the rest left to be
     * picked — the way the demo has them.
     */
    private function seedRecipes(): void
    {
        $categories = [$this->category('breakfasts'), $this->category('lunches'), $this->category('desserts')];
        $nutrients = [$this->nutrient('Iron'), $this->nutrient('Protein'), $this->nutrient('Fibre'), $this->nutrient('Calcium')];
        $pictures = array_map(fn (string $name): MediaFile => $this->picture("media/ab/cd/{$name}.jpg"), ['one', 'two', 'three', 'four']);
        $services = [$this->service('catering'), $this->service('classes')];
        $recipes = [];

        for ($i = 1; $i <= self::RECIPES; $i++) {
            $recipe = $this->recipe("recipe-{$i}", attributes: [
                'lead' => "The lead of recipe {$i}.",
                'gallery' => [['path' => $pictures[$i % 4]->path], ['path' => $pictures[($i + 1) % 4]->path]],
                'ingredients' => '<ul><li>Flour</li><li>Milk</li></ul>',
                'method' => "<p>Mix and bake for {$i} minutes.</p>",
                'nutrition' => ['calories' => ['en' => '320 kcal'], 'protein' => ['en' => '12 g']],
                'total_minutes' => 20 + $i,
                'servings' => 4,
            ]);
            $recipe->syncCategories([$categories[$i % 3]->getKey()]);
            $recipe->syncCategories([$nutrients[$i % 4]->getKey(), $nutrients[($i + 1) % 4]->getKey()], 'nutrients');

            if ($i % 2 === 0) {
                $recipe->syncRelated('services', 'service', [$services[0]->getKey(), $services[1]->getKey()]);
            }

            $recipes[] = $recipe;
        }

        foreach (array_slice($recipes, 0, (int) (self::RECIPES / 2)) as $i => $recipe) {
            $recipe->syncRelated('related', Recipe::TYPE, [
                $recipes[($i + 1) % self::RECIPES]->getKey(),
                $recipes[($i + 2) % self::RECIPES]->getKey(),
            ]);
        }
    }

    /** The first visitor after a deploy: an empty store, and nothing read in this process either. */
    private function cold(): void
    {
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
    }
}
