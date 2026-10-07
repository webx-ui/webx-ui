<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;

/**
 * A dry run is the write, rolled back; one name is one nutrient; what the tools do not take is
 * refused; the bin can be undone and emptied by an agent too.
 */
final class McpDryRunTest extends TestCase
{
    #[Test]
    public function a_nutrient_by_a_name_already_taken_is_refused_and_a_shared_name_names_both(): void
    {
        $calcium = $this->nutrient('Calcium');

        $this->agent('recipe_nutrients_create', ['title' => 'calcium'])->assertHasErrors(['already exists: #'.$calcium->id]);
        $this->assertSame(1, RecipeNutrient::query()->count());

        // A pair a site already holds: named by id from then on.
        $twin = $this->nutrient('Iron');
        $other = $this->nutrient('Iron');

        $this->agent('recipes_list', ['nutrient' => 'Iron'])->assertHasErrors(["#{$twin->id}, #{$other->id}"]);
    }

    #[Test]
    public function a_category_dry_run_is_refused_on_a_taken_address_and_shows_the_one_it_would_have(): void
    {
        $this->category('breakfast');
        $soups = $this->category('soups');

        $this->agent('recipe_categories_update', ['category' => $soups->id, 'values' => ['slug' => ['en' => 'breakfast']], 'dry_run' => true])
            ->assertHasErrors(['slug']);

        $dry = $this->content($this->agent('recipe_categories_update', ['category' => $soups->id, 'values' => ['slug' => ['en' => 'hot-soups']], 'dry_run' => true]));

        $this->assertSame(['en' => '/recipes/hot-soups'], $dry['category']['paths']);
        $this->assertSame('soups', RecipeCategory::query()->findOrFail($soups->id)->getTranslation('slug', 'en'));
    }

    #[Test]
    public function a_taken_address_is_refused_by_the_dry_run_of_create(): void
    {
        $this->recipe('porridge');

        $this->agent('recipes_create', ['title' => 'Porridge', 'dry_run' => true])->assertHasErrors(['slug']);

        $dry = $this->content($this->agent('recipes_create', ['title' => 'Pancakes', 'dry_run' => true]));

        $this->assertNull($dry['recipe']['id']);
        $this->assertSame(1, Recipe::query()->withTrashed()->count());
    }

    #[Test]
    public function unknown_arguments_and_fields_are_refused(): void
    {
        $recipe = $this->recipe('porridge');

        $this->agent('recipes_create', ['title' => 'Pancakes', 'servings' => 2])->assertHasErrors(['recipes_create has no argument [servings]']);
        $this->agent('recipes_update', ['recipe' => $recipe->id, 'values' => ['difficulty' => 'easy']])->assertHasErrors(['recipes_update has no field [difficulty]']);
    }

    #[Test]
    public function a_recipe_in_the_bin_is_restored_or_purged(): void
    {
        $kept = $this->recipe('porridge');
        $gone = $this->recipe('pancakes');

        $this->agent('recipes_purge', ['recipe' => $kept->id])->assertHasErrors(['not in the bin']);

        $kept->delete();
        $gone->delete();

        $restored = $this->content($this->agent('recipes_restore', ['recipe' => $kept->id]));

        $this->assertSame('/recipes/porridge', $restored['recipe']['urls']['en']['path']);

        $this->agent('recipes_purge', ['recipe' => $gone->id])->assertOk();
        $this->assertNull(Recipe::withTrashed()->find($gone->id));
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
