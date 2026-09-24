<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Recipes\Models\Recipe;

/**
 * Similar recipes (§5.6): chosen by hand and never topped up, or picked by what they share — a
 * category two points, a service two, a nutrient one — in one query.
 */
final class SimilarTest extends TestCase
{
    #[Test]
    public function the_chosen_ones_are_shown_in_their_order_and_not_topped_up(): void
    {
        $recipe = $this->recipe('porridge');
        $breakfasts = $this->category('breakfasts');
        $a = $this->recipe('a');
        $b = $this->recipe('b');
        $shared = $this->recipe('shared');

        foreach ([$recipe, $shared] as $inCategory) {
            $inCategory->syncCategories([$breakfasts->id]);
        }

        $recipe->syncRelated('related', 'recipe', [$b->id, $a->id]);

        $this->assertSame([$b->id, $a->id], $this->ids($recipe->refresh()->similar(3)));
    }

    #[Test]
    public function the_chosen_ones_the_site_does_not_show_drop_out(): void
    {
        $recipe = $this->recipe('porridge');
        $shown = $this->recipe('shown');
        $draft = $this->recipe('draft', published: false);
        $binned = $this->recipe('binned');

        $recipe->syncRelated('related', 'recipe', [$draft->id, $binned->id, $shown->id]);
        $binned->delete();

        $this->assertSame([$shown->id], $this->ids($recipe->refresh()->similar(3)));
    }

    #[Test]
    public function with_nothing_chosen_they_are_picked_by_points(): void
    {
        $breakfasts = $this->category('breakfasts');
        $quick = $this->category('quick');
        $iron = $this->nutrient('Iron');
        $fibre = $this->nutrient('Fibre');
        $implants = $this->service('implants');

        $recipe = $this->recipe('porridge');
        $recipe->syncCategories([$breakfasts->id, $quick->id]);
        $recipe->syncCategories([$iron->id, $fibre->id], 'nutrients');
        $recipe->syncRelated('services', 'service', [$implants->id]);

        // A category and a service: four.
        $service = $this->recipe('service-and-category');
        $service->syncCategories([$breakfasts->id]);
        $service->syncRelated('services', 'service', [$implants->id]);

        // Two categories: four too — a tie, and ties go to the order of the list.
        $twoCategories = $this->recipe('two-categories');
        $twoCategories->syncCategories([$breakfasts->id, $quick->id]);

        // Two nutrients: two.
        $nutrients = $this->recipe('two-nutrients');
        $nutrients->syncCategories([$iron->id, $fibre->id], 'nutrients');

        // One nutrient: one.
        $one = $this->recipe('one-nutrient');
        $one->syncCategories([$iron->id], 'nutrients');

        // Nothing shared: not similar at all.
        $this->recipe('nothing');

        // Shares a lot, but is a draft.
        $draft = $this->recipe('draft', published: false);
        $draft->syncCategories([$breakfasts->id, $quick->id]);

        $this->assertSame(
            [$service->id, $twoCategories->id, $nutrients->id, $one->id],
            $this->ids($recipe->refresh()->similar(10)),
        );
        $this->assertSame([$service->id, $twoCategories->id], $this->ids($recipe->similar(2)));
    }

    #[Test]
    public function a_recipe_that_shares_nothing_has_nothing_similar(): void
    {
        $recipe = $this->recipe('porridge');
        $this->recipe('other');

        $this->assertSame([], $this->ids($recipe->similar(3)));
    }

    /**
     * @param  iterable<Recipe>  $recipes
     * @return list<int>
     */
    private function ids(iterable $recipes): array
    {
        $ids = [];

        foreach ($recipes as $recipe) {
            $ids[] = (int) $recipe->id;
        }

        return $ids;
    }
}
