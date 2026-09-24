<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeNutrient;

/**
 * The API the panel is written against (§5.10) — the shapes are a contract with the npm half,
 * which was written in parallel from the spec, so the tests read the keys one by one.
 *
 * Values are read out of the array rather than by path: `nutrition.calories` is one name, and a
 * dotted path would look for a nested key that is not there (CLAUDE.md §4).
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_list_carries_the_rows_and_what_it_can_be_narrowed_to(): void
    {
        $breakfasts = $this->category('breakfasts');
        $iron = $this->nutrient('Iron');
        $implants = $this->service('implants');

        $porridge = $this->recipe('porridge', attributes: ['total_minutes' => 20]);
        $porridge->syncCategories([$breakfasts->id]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $row = $response->json('data.0');

        $this->assertSame([
            'id', 'title', 'slug', 'path', 'url', 'cover', 'minutes', 'status', 'position', 'categories',
            'published_at', 'updated_at', 'deleted_at', 'revision',
        ], array_keys($row));
        $this->assertSame('Porridge', $row['title']);
        $this->assertSame('recipes/porridge', $row['path']);
        $this->assertStringEndsWith('/recipes/porridge', (string) $row['url']);
        $this->assertNull($row['cover']);
        $this->assertSame(20, $row['minutes']);
        $this->assertSame('published', $row['status']);
        $this->assertSame([['id' => $breakfasts->id, 'title' => 'Breakfasts']], $row['categories']);

        $this->assertSame([['id' => $breakfasts->id, 'title' => 'Breakfasts']], $response->json('filters.categories'));
        $this->assertSame([['id' => $iron->id, 'title' => 'Iron']], $response->json('filters.nutrients'));
        $this->assertSame([['id' => $implants->id, 'title' => 'Implants']], $response->json('filters.services'));
    }

    #[Test]
    public function the_list_is_narrowed_by_category_nutrient_service_status_and_words(): void
    {
        $breakfasts = $this->category('breakfasts');
        $iron = $this->nutrient('Iron');
        $implants = $this->service('implants');

        $porridge = $this->recipe('porridge');
        $porridge->syncCategories([$breakfasts->id]);
        $liver = $this->recipe('liver');
        $liver->syncCategories([$iron->id], 'nutrients');
        $soup = $this->recipe('soup');
        $soup->syncRelated('services', 'service', [$implants->id]);
        $this->recipe('draft-cake', published: false);

        $editor = $this->editor();
        $titles = fn (string $query): array => array_column(
            (array) $this->actingAs($editor, 'cms')->getJson($this->api().$query)->assertOk()->json('data'),
            'title',
        );

        $this->assertSame(['Porridge'], $titles('?category='.$breakfasts->id));
        $this->assertSame(['Liver'], $titles('?nutrient='.$iron->id));
        $this->assertSame(['Soup'], $titles('?service='.$implants->id));
        $this->assertSame(['Draft cake'], $titles('?status=draft'));
        $this->assertSame(['Soup'], $titles('?q=sou'));
        $this->assertSame(['Soup'], $titles('?search=sou'));
    }

    #[Test]
    public function a_new_recipe_is_a_draft_with_its_address_held_and_the_form_around_it(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'Oat porridge'])
            ->assertCreated();

        $this->assertSame(['recipe', 'values', 'revision', 'prefix', 'preview_url'], array_keys((array) $response->json('data')));
        $this->assertSame('draft', $response->json('data.recipe.status'));
        $this->assertSame('recipes/oat-porridge', $response->json('data.recipe.path'));
        $this->assertSame('recipes', $response->json('data.prefix'));
        $this->assertStringContainsString('_preview/recipe/', (string) $response->json('data.preview_url'));

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertSame(['en' => 'Oat porridge'], $values['title']);
        $this->assertSame([], $values['gallery']);
        $this->assertSame([], $values['nutrition.calories']);
        $this->assertSame([], $values['categories']);
        $this->assertSame([], $values['nutrients']);
        $this->assertSame([], $values['services']);
        $this->assertSame([], $values['related']);
    }

    #[Test]
    public function an_address_somebody_holds_refuses_the_new_recipe_and_leaves_no_row(): void
    {
        $this->category('breakfasts');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'Breakfasts'])
            ->assertUnprocessable();

        $this->assertSame(0, Recipe::withTrashed()->count());
    }

    #[Test]
    public function the_editor_saves_every_field_of_the_screen(): void
    {
        $recipe = $this->recipe('porridge');
        $picture = $this->picture();
        $editor = $this->editor();

        $revision = $this->actingAs($editor, 'cms')->getJson($this->api($recipe->id))->json('data.revision');

        $response = $this->actingAs($editor, 'cms')->putJson($this->api($recipe->id), [
            'revision' => $revision,
            'values' => [
                'lead' => ['en' => 'Warm and quick.'],
                'gallery' => [['path' => $picture->path, 'url' => 'https://elsewhere.test/stale.jpg']],
                'ingredients' => ['en' => '<ul><li>Oats</li><li>Milk</li></ul>'],
                'method' => ['en' => '<ol><li>Boil.</li></ol>'],
                'nutrition.calories' => ['en' => '320 kcal'],
                'nutrition.fiber' => ['en' => '6 g'],
                'total_minutes' => 15,
                'servings' => 2,
            ],
        ])->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertSame([['path' => $picture->path]], $values['gallery']);
        $this->assertSame(['en' => '320 kcal'], $values['nutrition.calories']);
        $this->assertSame(['en' => '6 g'], $values['nutrition.fiber']);
        $this->assertSame([], $values['nutrition.fat']);
        $this->assertSame(15, $values['total_minutes']);
        $this->assertSame(2, $values['servings']);
        $this->assertSame('modified', $response->json('data.recipe.status'));
        $this->assertNotNull($response->json('data.recipe.cover.thumb'));
    }

    #[Test]
    public function a_save_over_somebody_elses_is_refused(): void
    {
        $recipe = $this->recipe('porridge');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($recipe->id), ['revision' => 'stale', 'values' => ['lead' => ['en' => 'x']]])
            ->assertStatus(409)
            ->assertJsonPath('data.recipe.id', $recipe->id);
    }

    #[Test]
    public function a_refused_save_leaves_nothing_behind(): void
    {
        $recipe = $this->recipe('porridge');
        $category = $this->category('breakfasts');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($recipe->id), ['values' => [
                'categories' => [$category->id],
                'servings' => 1000,
            ]])
            ->assertUnprocessable();

        $this->assertFalse($recipe->refresh()->hasDraft());
    }

    #[Test]
    public function the_order_is_one_list_and_a_category_does_not_change_it(): void
    {
        $a = $this->recipe('a');
        $b = $this->recipe('b');
        $c = $this->recipe('c');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertSame([$c->id, $a->id, $b->id], Recipe::query()->orderBy('position')->pluck('id')->all());
    }

    #[Test]
    public function the_two_kinds_of_category_answer_at_their_own_paths(): void
    {
        $this->category('breakfasts');
        $this->nutrient('Iron');

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->getJson('/api/cms/recipes/categories')->assertOk()->assertJsonPath('data.0.title', ['en' => 'Breakfasts']);
        $this->actingAs($editor, 'cms')->getJson('/api/cms/recipes/nutrients')->assertOk()->assertJsonPath('data.0.title', ['en' => 'Iron']);

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/recipes/nutrients', ['title' => 'Fibre'])
            ->assertCreated();

        $this->assertSame(['Iron', 'Fibre'], array_map(
            static fn ($row): string => (string) $row->getTranslation('title', 'en'),
            RecipeNutrient::query()->ordered()->get()->all(),
        ));
    }

    #[Test]
    public function the_screens_are_described_with_the_seo_card_patched_in(): void
    {
        $editor = $this->editor();

        $root = (array) $this->actingAs($editor, 'cms')->getJson('/api/cms/screens/recipes.form')->assertOk()->json('data.root');
        $tabs = $root[0]['children'];

        $this->assertSame(['recipe', 'settings', 'seo', 'history'], array_column($tabs, 'id'));
        $this->assertSame('seo-card', $tabs[2]['children'][0]['id']);

        $this->actingAs($editor, 'cms')->getJson('/api/cms/screens/recipes.category-form')->assertOk();
        $this->actingAs($editor, 'cms')->getJson('/api/cms/screens/recipes.nutrient-form')->assertOk();
    }
}
