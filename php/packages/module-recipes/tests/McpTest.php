<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Routing\Models\Route;

/**
 * Recipes by their other doors (§5.11): the same list, the same screen, the same draft, the same
 * order code.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_three_sections_offer_their_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['recipes_list', 'recipes_get', 'recipes_create', 'recipes_update', 'recipes_publish', 'recipes_unpublish', 'recipes_delete', 'recipes_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('recipes')),
        );

        $this->assertSame(
            ['recipe_categories_list', 'recipe_categories_create', 'recipe_categories_update', 'recipe_categories_delete', 'recipe_categories_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('recipe-categories')),
        );

        $this->assertSame(
            ['recipe_nutrients_list', 'recipe_nutrients_create', 'recipe_nutrients_update', 'recipe_nutrients_delete', 'recipe_nutrients_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('recipe-nutrients')),
        );

        $this->assertSame(['recipes.view', 'recipes.manage'], $registry->tool('recipes_list')->permissions());
        $this->assertSame(['recipes.manage'], $registry->tool('recipes_reorder')->permissions());
        $this->assertSame(['recipes.categories.manage'], $registry->tool('recipe_nutrients_create')->permissions());

        // Nutrients have no address, so no slug is offered.
        $this->assertArrayHasKey('slug', $registry->tool('recipe_categories_create')->tool->inputSchema['properties'] ?? []);
        $this->assertArrayNotHasKey('slug', $registry->tool('recipe_nutrients_create')->tool->inputSchema['properties'] ?? []);

        // The ingredients are a list, and the tool says so: the markup reads <li>.
        $this->assertStringContainsString('<li>', $registry->tool('recipes_update')->tool->description);
        $this->assertStringContainsString('<li>', $registry->tool('recipes_create')->tool->description);

        $this->assertContains('recipes://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['recipes.view']);

        $this->agent('recipes_list', [], $reader)->assertOk();
        $this->agent('recipes_create', ['title' => 'Porridge'], $reader)->assertHasErrors(['[recipes.manage]']);
    }

    #[Test]
    public function create_writes_a_draft_and_the_links_wait_for_publishing(): void
    {
        $breakfast = $this->category('breakfast');
        $iron = $this->nutrient('Iron');
        $plan = $this->service('nutrition-plan');
        $other = $this->recipe('lentil-soup');

        $created = $this->content($this->agent('recipes_create', [
            'title' => 'Oatmeal with berries',
            'values' => [
                'lead' => 'Ten minutes.',
                'ingredients' => '<ul><li>80 g oats</li><li>250 ml milk</li></ul>',
                'nutrition' => ['calories' => '380 kcal', 'fiber' => ['en' => '8 g']],
                'total_minutes' => 10,
                'servings' => 1,
                'categories' => [$breakfast->id],
                'nutrients' => ['Iron'],
                'services' => ['/services/nutrition-plan'],
                'related' => ['/recipes/lentil-soup'],
            ],
        ]));

        $this->assertSame('draft', $created['recipe']['status']);
        $this->assertSame('/recipes/oatmeal-with-berries', $created['recipe']['urls']['en']['path']);
        // A plain string is the default language, not the language of the request.
        $this->assertSame(['en' => 'Ten minutes.'], $created['values']['lead']);
        $this->assertSame(['en' => '380 kcal'], $created['values']['nutrition.calories']);
        $this->assertSame(['en' => '8 g'], $created['values']['nutrition.fiber']);
        $this->assertStringContainsString('<li>80 g oats</li>', $created['values']['ingredients']['en']);
        $this->assertSame([$breakfast->id], $created['values']['categories']);
        $this->assertSame([$plan->id], $created['values']['services']);
        $this->assertSame([$other->id], $created['values']['related']);

        $recipe = Recipe::query()->findOrFail($created['recipe']['id']);

        // Everything waits in the draft (§5.14): nothing is linked on the site yet.
        $this->assertSame([], $recipe->relatedIds('services'));
        $this->assertSame([], $recipe->categoryIds());

        $published = $this->content($this->agent('recipes_publish', ['recipe' => $recipe->id]));

        $this->assertSame('published', $published['recipe']['status']);
        $recipe->refresh();
        $this->assertSame([$plan->id], $recipe->relatedIds('services'));
        $this->assertSame([$other->id], $recipe->relatedIds('related'));
        $this->assertSame([$breakfast->id], $recipe->categoryIds());
        $this->assertSame([$iron->id], $recipe->categoryIds('nutrients'));
    }

    #[Test]
    public function a_refused_create_leaves_nothing_behind(): void
    {
        $this->agent('recipes_create', [
            'title' => 'Porridge',
            'values' => ['services' => [999]],
        ])->assertHasErrors(['[999]']);

        $this->agent('recipes_create', [
            'title' => 'Porridge',
            'values' => ['total_minutes' => 'a while'],
        ])->assertHasErrors(['total_minutes']);

        $this->assertSame(0, Recipe::withTrashed()->count());
        $this->assertSame(0, Route::query()->where('path', 'recipes/porridge')->count());
    }

    #[Test]
    public function update_names_services_and_similar_recipes_by_address_and_refuses_itself(): void
    {
        $recipe = $this->recipe('porridge');
        $plan = $this->service('nutrition-plan');
        $soup = $this->recipe('lentil-soup');

        $this->agent('recipes_update', ['recipe' => '/recipes/porridge', 'values' => ['services' => ['/services/nothing-here']]])
            ->assertHasErrors(['/services/nothing-here']);
        $this->agent('recipes_update', ['recipe' => $recipe->id, 'values' => ['related' => [$recipe->id]]])
            ->assertHasErrors(['not similar to itself']);
        $this->agent('recipes_update', ['recipe' => $recipe->id, 'values' => ['nutrition.sugar' => '1 g']])
            ->assertHasErrors(['nutrition.sugar']);
        $this->agent('recipes_update', ['recipe' => $recipe->id, 'values' => ['blocks' => []]])
            ->assertHasErrors(['no blocks']);

        $got = $this->content($this->agent('recipes_update', [
            'recipe' => '/recipes/porridge',
            'values' => ['services' => [(string) $plan->id], 'related' => ['/recipes/lentil-soup']],
        ]));

        $this->assertSame([$plan->id], $got['values']['services']);
        $this->assertSame([$soup->id], $got['values']['related']);
        $this->assertSame('modified', $got['recipe']['status']);
    }

    #[Test]
    public function a_write_over_somebody_elses_is_refused(): void
    {
        $recipe = $this->recipe('porridge');
        $revision = $this->content($this->agent('recipes_get', ['recipe' => $recipe->id]))['revision'];

        $this->agent('recipes_update', ['recipe' => $recipe->id, 'values' => ['lead' => 'First.'], 'revision' => $revision])->assertOk();
        $this->agent('recipes_update', ['recipe' => $recipe->id, 'values' => ['lead' => 'Second.'], 'revision' => $revision])
            ->assertHasErrors(['changed since you read it']);
    }

    #[Test]
    public function the_list_narrows_by_category_nutrient_and_service(): void
    {
        $breakfast = $this->category('breakfast');
        $iron = $this->nutrient('Iron');
        $plan = $this->service('nutrition-plan');

        $porridge = $this->recipe('porridge');
        $porridge->syncCategories([$breakfast->id]);
        $soup = $this->recipe('lentil-soup');
        $soup->syncCategories([$iron->id], 'nutrients');
        $soup->syncRelated('services', 'service', [$plan->id]);
        $this->recipe('salad', published: false);

        $ids = fn (array $arguments): array => array_column($this->content($this->agent('recipes_list', $arguments))['recipes'], 'id');

        $this->assertCount(3, $ids([]));
        $this->assertSame([$porridge->id], $ids(['category' => 'recipes/breakfast']));
        $this->assertSame([$soup->id], $ids(['nutrient' => 'Iron']));
        $this->assertSame([$soup->id], $ids(['service' => '/services/nutrition-plan']));
        $this->assertCount(1, $ids(['status' => 'draft']));
    }

    #[Test]
    public function reorder_is_the_one_order_there_is(): void
    {
        $first = $this->recipe('first');
        $second = $this->recipe('second');
        $third = $this->recipe('third');

        $this->agent('recipes_reorder', ['recipes' => [$third->id, '/recipes/first'], 'dry_run' => true])->assertOk();
        $this->assertSame([$first->id, $second->id, $third->id], Recipe::query()->orderedIn()->pluck('id')->all());

        $listed = $this->content($this->agent('recipes_reorder', ['recipes' => [$third->id, '/recipes/first']]));

        $this->assertSame([$third->id, $first->id], array_slice(array_column($listed['recipes'], 'id'), 0, 2));
        $this->assertArrayNotHasKey('category', $this->app->make(ToolRegistry::class)->tool('recipes_reorder')->tool->inputSchema['properties']);
    }

    #[Test]
    public function unpublish_and_delete(): void
    {
        $recipe = $this->recipe('porridge');

        $this->assertSame('unpublished', $this->content($this->agent('recipes_unpublish', ['recipe' => $recipe->id]))['recipe']['status']);

        $this->agent('recipes_delete', ['recipe' => $recipe->id, 'dry_run' => true])->assertOk();
        $this->assertFalse($recipe->refresh()->trashed());

        $this->agent('recipes_delete', ['recipe' => '/recipes/porridge'])->assertOk();
        $this->assertTrue($recipe->refresh()->trashed());
        $this->assertSame([$recipe->id], array_column($this->content($this->agent('recipes_list', ['trashed' => true]))['recipes'], 'id'));
        $this->agent('recipes_update', ['recipe' => $recipe->id, 'values' => ['lead' => 'x']])->assertHasErrors(['in the bin']);
    }

    #[Test]
    public function the_catalogue_lists_categories_with_their_recipes_the_rest_and_the_nutrients(): void
    {
        $this->useLocales('en', 'ru');

        $breakfast = $this->category('breakfast');
        $hidden = $this->category('secret', visible: false);
        $both = $this->recipe('porridge', attributes: ['title' => ['en' => 'Porridge', 'ru' => 'Каша']]);
        $both->syncCategories([$breakfast->id, $hidden->id]);
        $draft = $this->recipe('salad', published: false);
        $draft->syncCategories([$breakfast->id]);
        $loose = $this->recipe('soup');
        $this->nutrient('Iron');

        $catalog = ($this->resource('recipes://catalog')->handler)();

        $this->assertSame('recipes', $catalog['prefix']);
        $this->assertStringEndsWith('/recipes', (string) $catalog['index_url']);
        $this->assertSame(['Breakfast', 'Secret'], array_column($catalog['categories'], 'title'));
        $this->assertFalse($catalog['categories'][1]['visible']);
        $this->assertSame([$both->id, $draft->id], array_column($catalog['categories'][0]['recipes'], 'id'));
        $this->assertSame([$both->id], array_column($catalog['categories'][1]['recipes'], 'id'));
        $this->assertSame('draft', $catalog['categories'][0]['recipes'][1]['status']);
        $this->assertSame(['en', 'ru'], $catalog['categories'][0]['recipes'][0]['written_in']);
        $this->assertSame(['en'], $catalog['categories'][0]['recipes'][1]['written_in']);
        $this->assertStringEndsWith('/recipes/porridge', (string) $catalog['categories'][0]['recipes'][0]['url']);
        $this->assertSame([$loose->id], array_column($catalog['uncategorised'], 'id'));
        $this->assertSame(['Iron'], array_column($catalog['nutrients'], 'title'));
    }

    #[Test]
    public function the_catalogue_has_no_index_when_it_is_switched_off(): void
    {
        $this->app['config']->set('webx-recipes.index', false);

        $this->assertNull(($this->resource('recipes://catalog')->handler)()['index_url']);
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
