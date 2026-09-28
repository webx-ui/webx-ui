<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Vacancies\Models\VacancyCategory;

/**
 * Categories without an address (decision 2) whose key is kept all the same (decision 11): made
 * from the title, one per language among the categories of vacancies — the bin counted — and a
 * category holding vacancies does not go.
 */
final class CategoriesTest extends TestCase
{
    #[Test]
    public function the_key_is_made_from_the_title_and_kept(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('categories'), ['title' => ['en' => 'Customer support']])
            ->assertCreated();

        $category = VacancyCategory::query()->firstOrFail();

        $this->assertSame('customer-support', $category->getTranslation('slug', 'en'));

        $category->update(['title' => ['en' => 'Support']]);

        $this->assertSame('customer-support', $category->refresh()->getTranslation('slug', 'en'));
    }

    #[Test]
    public function a_key_taken_in_the_same_language_is_refused_under_its_language_the_bin_counted(): void
    {
        $this->useLocales('en', 'uk');

        $this->category('sales')->delete();
        $development = $this->category('development');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('categories'), ['title' => ['en' => 'Sales again'], 'slug' => ['en' => 'sales']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug.en']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api('categories/'.$development->id), ['values' => ['slug' => ['en' => 'development', 'uk' => 'sales']]])
            ->assertOk();

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api('categories/'.$development->id), ['values' => ['slug' => ['en' => 'Not A Key']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug.en']);
    }

    #[Test]
    public function a_category_with_vacancies_does_not_go_to_the_bin(): void
    {
        $category = $this->category('design');
        $this->vacancy('designer')->syncCategories([$category->id]);

        $response = $this->actingAs($this->editor(), 'cms')->deleteJson($this->api('categories/'.$category->id));

        $this->assertContains($response->getStatusCode(), [409, 422]);
        $this->assertFalse($category->refresh()->trashed());
    }

    #[Test]
    public function the_screen_of_a_category_is_its_title_key_and_visibility(): void
    {
        $category = $this->category('design');

        $values = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('categories/'.$category->id))
            ->assertOk()
            ->json('data.values');

        $this->assertIsArray($values);
        $this->assertSame(['title', 'slug', 'is_visible'], array_keys($values));
    }
}
