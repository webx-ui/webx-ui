<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\BlockShapes;
use WebxUi\Blocks\Models\Block;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Rendering\Cards;
use WebxUi\Recipes\Rendering\RecipeCard;

/**
 * The recipe card (§5 of the components spec): its category and services as links, built for a
 * whole list at once, and the `recipe-card` place a site can redraw from the panel — the partial
 * until a component is published, the component after, the partial again once it is deleted.
 */
final class CardTest extends TestCase
{
    #[Test]
    public function a_card_carries_its_categories_and_services_as_links_in_the_order_chosen(): void
    {
        $soups = $this->category('soups');
        $quick = $this->category('quick');
        $hidden = $this->category('hidden', visible: false);
        $diet = $this->service('diet');
        $implants = $this->service('implants');
        $off = $this->service('off', published: false);

        $recipe = $this->recipe('borscht');
        $recipe->syncCategories([$quick->id, $hidden->id, $soups->id]);
        $recipe->syncRelated('services', 'service', [$implants->id, $off->id, $diet->id]);

        $card = $this->cards([$recipe])[0];

        // The ids stay — the block's filter compares them — hidden ones included.
        $this->assertSame([$quick->id, $hidden->id, $soups->id], $card['categories']);
        $this->assertSame([
            ['id' => $quick->id, 'title' => 'Quick', 'url' => 'http://localhost/recipes/quick'],
            ['id' => $soups->id, 'title' => 'Soups', 'url' => 'http://localhost/recipes/soups'],
        ], $card['category_links']);
        $this->assertSame([
            ['id' => $implants->id, 'title' => 'Implants', 'url' => 'http://localhost/services/implants'],
            ['id' => $diet->id, 'title' => 'Diet', 'url' => 'http://localhost/services/diet'],
        ], $card['service_links']);
    }

    #[Test]
    public function a_longer_list_costs_no_more_queries(): void
    {
        $soups = $this->category('soups');
        $diet = $this->service('diet');

        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $slug) {
            $recipe = $this->recipe($slug);
            $recipe->syncCategories([$soups->id]);
            $recipe->syncRelated('services', 'service', [$diet->id]);
        }

        $two = $this->queries(fn (): array => $this->cards(Recipe::query()->with(Cards::RELATIONS)->whereIn('id', [1, 2])->get()->all()));
        $six = $this->queries(fn (): array => $this->cards(Recipe::query()->with(Cards::RELATIONS)->get()->all()));

        $this->assertSame($two, $six);
    }

    #[Test]
    public function the_standard_card_prints_the_main_category_and_the_services(): void
    {
        $soups = $this->category('soups');
        $recipe = $this->recipe('borscht', attributes: ['total_minutes' => 40]);
        $recipe->syncCategories([$soups->id]);
        $recipe->syncRelated('services', 'service', [$this->service('diet')->id]);

        $page = (string) $this->get('/recipes')->assertOk()->getContent();

        $this->assertStringContainsString('<a class="wx-recipes__category" href="http://localhost/recipes/soups">Soups</a>', $page);
        $this->assertStringContainsString('<a href="http://localhost/services/diet">Diet</a>', $page);
        $this->assertStringContainsString('class="wx-recipes__time"', $page);
    }

    #[Test]
    public function the_card_is_declared_with_a_shape_and_a_real_sample(): void
    {
        $declared = $this->app->make(BlockComponents::class)->get(RecipeCard::SLUG);

        $this->assertNotNull($declared);
        $this->assertSame('recipes', $declared['module']);
        $this->assertSame(RecipeCard::FALLBACK, $declared['fallback']);

        $shapes = $this->app->make(BlockShapes::class);
        $names = array_column($shapes->fields(RecipeCard::SHAPE), 'name');

        // The shape describes every key a card has, and nothing it does not.
        $this->recipe('borscht');
        $sample = $shapes->sample(RecipeCard::SHAPE);

        $this->assertIsArray($sample);
        $this->assertSame(array_keys($sample), $names);
        $this->assertSame('Borscht', $sample['title']);
    }

    #[Test]
    public function a_customised_card_prints_once_published_and_the_partial_returns_once_deleted(): void
    {
        $soups = $this->category('soups');

        foreach (['borscht', 'shchi'] as $slug) {
            $this->recipe($slug)->syncCategories([$soups->id]);
        }

        $editor = $this->editor(['blocks.view', 'blocks.manage']);

        $id = (int) $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/components/recipe-card/customise')
            ->assertCreated()
            ->json('data.id');

        // A draft changes nothing on the site.
        $this->assertStringContainsString('class="wx-recipes__name"', (string) $this->get('/recipes')->getContent());

        $block = Block::query()->findOrFail($id);
        $block->saveVersion(['template' => '<li class="site-card">{{ $card[\'title\'] }} · {{ $card[\'category_links\'][0][\'title\'] ?? \'\' }}</li>']);

        $this->actingAs($editor, 'cms')->postJson("/api/cms/blocks/{$id}/publish")->assertOk();

        $index = (string) $this->get('/recipes')->assertOk()->getContent();
        $this->assertStringContainsString('<li class="site-card">Borscht · Soups</li>', $index);
        $this->assertStringNotContainsString('class="wx-recipes__name"', $index);

        $this->assertStringContainsString('<li class="site-card">Borscht · Soups</li>', (string) $this->get('/recipes/soups')->getContent());
        $this->assertStringContainsString('<li class="site-card">Shchi · Soups</li>', (string) $this->get('/recipes/borscht')->getContent());

        $this->actingAs($editor, 'cms')->deleteJson("/api/cms/blocks/{$id}")->assertNoContent();

        $index = (string) $this->get('/recipes')->getContent();
        $this->assertStringNotContainsString('site-card', $index);
        $this->assertStringContainsString('class="wx-recipes__name"', $index);
    }

    /**
     * @param  list<Recipe>  $recipes
     * @return list<array<string, mixed>>
     */
    private function cards(array $recipes): array
    {
        return $this->app->make(Cards::class)->recipes($recipes, 'en');
    }

    private function queries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
