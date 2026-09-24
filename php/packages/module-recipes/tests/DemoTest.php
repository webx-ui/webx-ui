<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Recipes\Demo\RecipesDemo;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Services\Models\Service;

/**
 * The demo recipes (§5.12): the rules they are there to show, the links to the demo services, and
 * both views of the block on one page.
 */
final class DemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->useLocales('en', 'ru');
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_asks_for_what_is_installed(): void
    {
        $this->assertSame(['media', 'services', 'blocks', 'pages'], $this->app->make(RecipesDemo::class)->requires());
    }

    #[Test]
    public function it_seeds_three_categories_five_nutrients_and_six_recipes(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(3, RecipeCategory::query()->count());
        $this->assertSame(5, RecipeNutrient::query()->count());
        $this->assertSame(6, Recipe::query()->count());
        $this->assertSame(5, Recipe::query()->whereNotNull('published_at')->count());
        $this->assertTrue(Block::query()->where('slug', 'recipes')->exists());

        $omelette = $this->named('green-omelette');
        // Two categories, the one named first the main one.
        $this->assertSame(['breakfast', 'salads'], $omelette->categories->map(static fn (RecipeCategory $category): string => (string) $category->getTranslation('slug', 'en'))->all());
        $this->assertSame([], $omelette->nutrition('en'));
        $this->assertSame([], $omelette->pictures());

        $oatmeal = $this->named('oatmeal-with-berries');
        $this->assertCount(2, $oatmeal->pictures());
        $this->assertSame([(int) $this->named('chia-pudding')->getKey()], $oatmeal->relatedIds('related'));
        $this->assertSame('380 ккал', $oatmeal->nutrition('ru')['calories'] ?? null);

        // Written in English only: no Russian address.
        $pumpkin = $this->named('pumpkin-soup');
        $this->assertTrue($pumpkin->hasUrlIn('en'));
        $this->assertFalse($pumpkin->hasUrlIn('ru'));

        $this->assertSame('draft', $this->named('buckwheat-salad')->status());
    }

    #[Test]
    public function two_recipes_are_linked_to_the_demo_services(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $slugs = fn (Recipe $recipe): array => Service::query()
            ->whereKey($recipe->relatedIds('services'))
            ->get()
            ->map(static fn (Service $service): string => (string) $service->getTranslation('slug', 'en'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['content-editing'], $slugs($this->named('oatmeal-with-berries')));
        $this->assertSame(['content-editing', 'seo-audit'], $slugs($this->named('lentil-soup')));
    }

    #[Test]
    public function the_page_has_a_showcase_of_one_category_and_the_catalogue_below_it(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $page = $this->get('/recipes-showcase')->assertOk();
        $page->assertSee('Quick breakfasts');
        $page->assertSee('Every recipe');
        $page->assertSee('Oatmeal with berries');
        // The draft is on no page.
        $page->assertDontSee('Warm buckwheat salad');
        $page->assertSee('page=2', false);

        $this->nextRequest();

        $this->get('/recipes-showcase?page=2')->assertOk()->assertSee('Pumpkin soup with ginger');
    }

    #[Test]
    public function a_recipe_page_carries_the_recipe_markup(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $markup = $this->jsonLd($this->get('/recipes/oatmeal-with-berries')->assertOk()->getContent() ?: '', 'Recipe');

        $this->assertSame('Oatmeal with berries', $markup['name']);
        $this->assertSame('PT10M', $markup['totalTime']);
        $this->assertCount(5, $markup['recipeIngredient']);
        $this->assertCount(4, $markup['recipeInstructions']);
        $this->assertSame('380 kcal', $markup['nutrition']['calories']);
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Recipe::withTrashed()->count());
        $this->assertSame(0, RecipeCategory::withTrashed()->count());
        $this->assertSame(0, RecipeNutrient::withTrashed()->count());
        $this->assertFalse(Page::withTrashed()->where('slug->en', 'recipes-showcase')->exists());
        $this->assertFalse(Block::query()->where('slug', 'recipes')->exists());
    }

    private function named(string $slug): Recipe
    {
        return Recipe::query()->where('slug->en', $slug)->firstOrFail();
    }

    private function nextRequest(string $locale = 'en'): void
    {
        $this->app->instance('request', Request::create('/'));
        $this->app->make(Locales::class)->use($locale);
    }
}
