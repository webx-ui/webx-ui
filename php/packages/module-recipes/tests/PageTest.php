<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Seo\RecipeMarkup;

/**
 * A recipe page (§5.5) and what it says about itself (§5.7): the parts, the `Recipe` markup, the
 * trail through the main category.
 */
final class PageTest extends TestCase
{
    #[Test]
    public function the_page_prints_every_part_the_recipe_has(): void
    {
        $breakfasts = $this->category('breakfasts');
        $iron = $this->nutrient('Iron');
        $implants = $this->service('implants');
        $picture = $this->picture();

        $recipe = $this->recipe('porridge', attributes: [
            'lead' => 'Warm and quick.',
            'gallery' => [['path' => $picture->path]],
            'ingredients' => '<ul><li>Oats</li><li>Milk</li></ul>',
            'method' => '<ol><li>Boil the milk.</li><li>Stir in the oats.</li></ol>',
            'nutrition' => ['calories' => ['en' => '320 kcal'], 'fiber' => ['en' => '6 g']],
            'total_minutes' => 75,
            'servings' => 2,
        ]);
        $recipe->syncCategories([$breakfasts->id]);
        $recipe->syncCategories([$iron->id], 'nutrients');
        $recipe->syncRelated('services', 'service', [$implants->id]);

        $page = $this->get('/recipes/porridge')->assertOk()->getContent();
        $this->assertIsString($page);

        $this->assertStringContainsString('<h1>Porridge</h1>', $page);
        $this->assertStringContainsString('Warm and quick.', $page);
        $this->assertStringContainsString('1 h 15 min', $page);
        $this->assertStringContainsString('Servings: 2', $page);
        $this->assertStringContainsString('href="http://localhost/recipes/breakfasts"', $page);
        $this->assertStringContainsString('Iron', $page);
        $this->assertStringContainsString('<li>Oats</li>', $page);
        $this->assertStringContainsString('Boil the milk.', $page);
        $this->assertStringContainsString('320 kcal', $page);
        $this->assertStringNotContainsString('Protein', $page);
        $this->assertStringContainsString('href="http://localhost/services/implants"', $page);
        $this->assertStringContainsString($picture->path, $page);

        $markup = $this->jsonLd($page, 'Recipe');

        $this->assertSame('Porridge', $markup['name']);
        $this->assertSame('Warm and quick.', $markup['description']);
        $this->assertSame('PT1H15M', $markup['totalTime']);
        $this->assertSame('2', $markup['recipeYield']);
        $this->assertSame('Breakfasts', $markup['recipeCategory']);
        $this->assertSame(['Oats', 'Milk'], $markup['recipeIngredient']);
        $this->assertSame(
            [['@type' => 'HowToStep', 'text' => 'Boil the milk.'], ['@type' => 'HowToStep', 'text' => 'Stir in the oats.']],
            $markup['recipeInstructions'],
        );
        $this->assertSame(['@type' => 'NutritionInformation', 'calories' => '320 kcal', 'fiberContent' => '6 g'], $markup['nutrition']);
        $this->assertCount(1, $markup['image']);

        $trail = $this->jsonLd($page, 'BreadcrumbList');
        $this->assertSame(['Home', 'Recipes', 'Breakfasts', 'Porridge'], array_column($trail['itemListElement'], 'name'));
    }

    #[Test]
    public function ingredients_and_steps_without_a_list_are_read_paragraph_by_paragraph(): void
    {
        $this->assertSame(['Oats', 'Milk and a pinch of salt'], RecipeMarkup::items('<p>Oats</p><p>Milk and  a pinch of <strong>salt</strong></p>'));
        $this->assertSame(['One', 'Two'], RecipeMarkup::items('<p>Ignored when there is a list</p><ul><li>One</li><li>Two</li></ul>'));
        $this->assertSame(['Каша'], RecipeMarkup::items('<ul><li>Каша</li></ul>'));
        $this->assertSame([], RecipeMarkup::items(''));

        $recipe = $this->recipe('porridge', attributes: [
            'ingredients' => '<p>Oats</p><p>Milk</p>',
            'method' => '<p>Boil.</p><p>Serve.</p>',
        ]);

        $markup = $this->app->make(RecipeMarkup::class)->of($recipe, 'en');

        $this->assertSame(['Oats', 'Milk'], $markup['recipeIngredient']);
        $this->assertSame(['Boil.', 'Serve.'], array_column($markup['recipeInstructions'], 'text'));
    }

    #[Test]
    public function the_total_time_is_iso_8601(): void
    {
        $this->assertSame('PT45M', RecipeMarkup::duration(45));
        $this->assertSame('PT1H15M', RecipeMarkup::duration(75));
        $this->assertSame('PT2H', RecipeMarkup::duration(120));
    }

    #[Test]
    public function an_empty_nutrition_is_no_nutrition_at_all(): void
    {
        $recipe = $this->recipe('porridge', attributes: ['nutrition' => ['calories' => ['en' => '  ']]]);

        $page = (string) $this->get('/recipes/porridge')->assertOk()->getContent();

        $this->assertArrayNotHasKey('nutrition', $this->jsonLd($page, 'Recipe'));
        $this->assertStringNotContainsString('Nutrition per serving', $page);
        $this->assertNull($recipe->getRawOriginal('nutrition'));
    }

    #[Test]
    public function the_nutrition_keeps_the_five_keys_and_prints_only_the_language_being_read(): void
    {
        $recipe = $this->recipe('porridge', attributes: ['nutrition' => [
            'calories' => ['en' => '320 kcal', 'ru' => ''],
            'protein' => ['ru' => '12 г'],
            'sugar' => ['en' => '3 g'],
        ]]);

        $this->assertSame(['calories' => ['en' => '320 kcal'], 'protein' => ['ru' => '12 г']], $recipe->nutrition);
        $this->assertSame(['calories' => '320 kcal'], $recipe->nutrition('en'));
        $this->assertSame(['protein' => '12 г'], $recipe->nutrition('ru'));
    }

    #[Test]
    public function a_draft_answers_404_and_a_recipe_in_a_hidden_category_answers(): void
    {
        $this->recipe('draft', published: false);
        $hidden = $this->category('hidden', visible: false);
        $shown = $this->recipe('porridge');
        $shown->syncCategories([$hidden->id]);

        $this->get('/recipes/draft')->assertNotFound();

        $page = (string) $this->get('/recipes/porridge')->assertOk()->getContent();

        // The hidden category is not a step of the trail, and not a link.
        $this->assertSame(['Home', 'Recipes', 'Porridge'], array_column($this->jsonLd($page, 'BreadcrumbList')['itemListElement'], 'name'));
        $this->assertStringNotContainsString('recipes/hidden', $page);
    }

    #[Test]
    public function a_service_the_site_does_not_show_is_not_printed(): void
    {
        $recipe = $this->recipe('porridge');
        $off = $this->service('off', published: false);
        $recipe->syncRelated('services', 'service', [$off->id]);

        $this->assertStringNotContainsString('services/off', (string) $this->get('/recipes/porridge')->getContent());
    }

    #[Test]
    public function similar_recipes_are_printed_under_it(): void
    {
        $breakfasts = $this->category('breakfasts');
        $recipe = $this->recipe('porridge');
        $other = $this->recipe('pancakes');

        foreach ([$recipe, $other] as $inCategory) {
            $inCategory->syncCategories([$breakfasts->id]);
        }

        $page = (string) $this->get('/recipes/porridge')->getContent();

        $this->assertStringContainsString('Similar recipes', $page);
        $this->assertStringContainsString('href="http://localhost/recipes/pancakes"', $page);
    }

    #[Test]
    public function a_recipe_is_in_the_sitemap_once_published(): void
    {
        $this->recipe('porridge');
        $this->recipe('draft', published: false);

        $sitemap = (string) $this->get('/sitemap-recipe.xml')->assertOk()->getContent();
        $routes = (string) $this->get('/sitemap-routes.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/recipes/porridge', $sitemap);
        $this->assertStringNotContainsString('/recipes/draft', $sitemap);
        $this->assertStringContainsString('<loc>http://localhost/recipes</loc>', $routes);
    }

    #[Test]
    public function publishing_writes_the_version_and_the_history_lists_it(): void
    {
        $recipe = $this->recipe('porridge', published: false);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api($recipe->id.'/publish'))->assertOk();

        $this->actingAs($editor, 'cms')->getJson($this->api($recipe->id.'/versions'))
            ->assertOk()
            ->assertJsonPath('data.0.number', 1)
            ->assertJsonPath('data.0.author', 'Editor');

        $this->assertSame(Recipe::STATUS_PUBLISHED, $recipe->refresh()->status());
    }
}
