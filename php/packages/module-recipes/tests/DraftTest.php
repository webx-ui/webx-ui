<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Recipes\Models\Recipe;

/**
 * Everything the editor chooses waits in the draft until it is published (§5.14): the text, and
 * also the categories, the nutrients, the services and the similar recipes. "Linked the recipe to
 * a service" shows on the site when the recipe is published, not when it is saved.
 */
final class DraftTest extends TestCase
{
    #[Test]
    public function categories_nutrients_and_relations_take_effect_on_publishing_not_on_saving(): void
    {
        $recipe = $this->recipe('porridge');
        $other = $this->recipe('pancakes');
        $breakfasts = $this->category('breakfasts');
        $iron = $this->nutrient('Iron');
        $implants = $this->service('implants');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api($recipe->id), ['values' => [
            'categories' => [$breakfasts->id],
            'nutrients' => [$iron->id],
            'services' => [$implants->id],
            'related' => [$other->id],
        ]])->assertOk()
            ->assertJsonPath('data.values.categories', [$breakfasts->id])
            ->assertJsonPath('data.values.nutrients', [$iron->id])
            ->assertJsonPath('data.values.services', [$implants->id])
            ->assertJsonPath('data.values.related', [$other->id])
            ->assertJsonPath('data.recipe.status', 'modified')
            // The list shows what the editor is working on.
            ->assertJsonPath('data.recipe.categories.0.title', 'Breakfasts');

        $recipe->refresh();

        $this->assertSame([], $recipe->categoryIds('categories'));
        $this->assertSame([], $recipe->categoryIds('nutrients'));
        $this->assertSame([], $recipe->relatedIds('services'));
        $this->assertSame([], $recipe->relatedIds('related'));

        $this->actingAs($editor, 'cms')->postJson($this->api($recipe->id.'/publish'))->assertOk()
            ->assertJsonPath('data.status', 'published');

        $recipe = Recipe::query()->findOrFail($recipe->id);

        $this->assertSame([$breakfasts->id], $recipe->categoryIds('categories'));
        $this->assertSame([$iron->id], $recipe->categoryIds('nutrients'));
        $this->assertSame([$implants->id], $recipe->relatedIds('services'));
        $this->assertSame([$other->id], $recipe->relatedIds('related'));
        $this->assertFalse($recipe->hasDraft());
    }

    #[Test]
    public function putting_the_categories_back_leaves_the_draft_as_it_was(): void
    {
        $recipe = $this->recipe('porridge');
        $breakfasts = $this->category('breakfasts');
        $recipe->syncCategories([$breakfasts->id]);
        $recipe->discardDraft();

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($recipe->id), ['values' => [
            'categories' => [$breakfasts->id],
        ]])->assertOk();

        $this->assertArrayNotHasKey(Recipe::DRAFT_CATEGORIES, $recipe->refresh()->draftValues());
    }

    #[Test]
    public function the_preview_shows_the_drafted_categories(): void
    {
        $recipe = $this->recipe('porridge');
        $breakfasts = $this->category('breakfasts');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($recipe->id), ['values' => [
            'categories' => [$breakfasts->id],
        ]])->assertOk();

        $recipe->refresh();

        $this->assertNull($recipe->mainRecipeCategory());
        $this->assertSame($breakfasts->id, $recipe->withDraft()->mainRecipeCategory()?->id);
    }

    #[Test]
    public function discarding_drops_the_waiting_categories_and_relations(): void
    {
        $recipe = $this->recipe('porridge');
        $breakfasts = $this->category('breakfasts');
        $implants = $this->service('implants');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api($recipe->id), ['values' => [
            'categories' => [$breakfasts->id],
            'services' => [$implants->id],
        ]])->assertOk();

        $this->actingAs($editor, 'cms')->postJson($this->api($recipe->id.'/discard'))->assertOk()
            ->assertJsonPath('data.values.categories', [])
            ->assertJsonPath('data.values.services', []);
    }
}
