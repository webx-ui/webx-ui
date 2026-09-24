<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;

/**
 * The section as the panel reaches it (§4.6, §4.7): the manifest, the permissions, the list with
 * its two orders, the form that creates and saves — in the shapes the panel is written against —
 * and the categories with no address.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_two_sections_are_in_the_manifest_under_one_group(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        /** @var array<int, array<string, mixed>> $modules */
        $modules = $response->json('data.modules');
        $ours = [];

        foreach ($modules as $module) {
            if (($module['group'] ?? null) === 'reviews') {
                $ours[(string) $module['id']] = $module;
            }
        }

        $this->assertSame(['reviews', 'review-categories'], array_keys($ours));
        $this->assertSame(['reviews.view', 'reviews.manage'], $ours['reviews']['permissions']);
        $this->assertSame(['reviews.categories.manage'], $ours['review-categories']['permissions']);

        $icons = array_column((array) $response->json('data.groups'), 'icon', 'id');
        $this->assertSame('star', $icons['reviews'] ?? null);
    }

    #[Test]
    public function a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $review = $this->review('Anna');

        $this->getJson($this->api())->assertUnauthorized();

        $viewer = $this->editor(['reviews.view']);

        $this->actingAs($viewer, 'cms')->getJson($this->api())->assertOk();
        $this->actingAs($viewer, 'cms')->getJson($this->api($review->id))->assertOk();
        $this->actingAs($viewer, 'cms')->postJson($this->api(), ['values' => ['name' => ['en' => 'Boris']]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->putJson($this->api($review->id), ['values' => ['published' => false]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->deleteJson($this->api($review->id))->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('reorder'), ['ids' => [$review->id]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson('/api/cms/reviews/categories', ['title' => 'Clinic'])->assertForbidden();

        // The categories are read by anybody who may open a review: the form files into them.
        $this->actingAs($viewer, 'cms')->getJson('/api/cms/reviews/categories')->assertOk();
    }

    #[Test]
    public function the_list_is_every_review_in_its_order_in_the_shape_of_the_spec(): void
    {
        $clinic = $this->category('Clinic');
        $this->picture();
        $a = $this->review('Anna', 'Анна довольна.', attributes: [
            'job_title' => ['en' => 'CEO'],
            'rating' => 4,
            'photo' => ['path' => 'media/ab/cd/anna.jpg'],
        ]);
        $b = $this->review('Boris');
        $c = $this->review('Vera', published: false);
        $a->syncCategories([$clinic->id]);
        DB::table('reviews')->where('id', $a->id)->update(['position' => 30]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame([$b->id, $c->id, $a->id], array_column((array) $response->json('data'), 'id'));
        $this->assertNull($response->json('meta'), 'no pages: the list is where reviews are put in order');

        $row = (array) $response->json('data.2');
        $this->assertSame(
            ['id', 'name', 'job_title', 'rating', 'photo', 'published', 'position', 'locales', 'categories', 'updated_at', 'deleted_at'],
            array_keys($row),
        );
        $this->assertSame('Anna', $row['name']);
        $this->assertSame('CEO', $row['job_title']);
        $this->assertSame(4, $row['rating']);
        $this->assertSame(['thumb'], array_keys((array) $row['photo']));
        $this->assertTrue($row['published']);
        $this->assertSame(['en', 'ru'], $row['locales']);
        $this->assertSame([['id' => $clinic->id, 'title' => 'Clinic']], $row['categories']);
        $this->assertNull($row['deleted_at']);

        $this->assertNull($response->json('data.0.photo'));
        $this->assertNull($response->json('data.0.rating'));
        $this->assertSame('', $response->json('data.0.job_title'));
        $this->assertFalse($response->json('data.1.published'));
        $this->assertSame([['id' => $clinic->id, 'title' => 'Clinic']], $response->json('filters.categories'));

        $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?search=bor')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?search=довольна')
            ->assertOk()
            ->assertJsonPath('data.0.id', $a->id);
    }

    #[Test]
    public function a_row_is_named_in_the_panels_language_then_the_default_then_by_its_number(): void
    {
        $nameless = Review::query()->create(['text' => ['en' => 'Words.']]);
        $russian = Review::query()->create(['name' => ['ru' => 'Анна'], 'text' => ['ru' => 'Слова.']]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame('#'.$nameless->id, $response->json('data.0.name'));
        $this->assertSame('#'.$russian->id, $response->json('data.1.name'), 'not in English, not in the default language');
        $this->assertSame(['en'], $response->json('data.0.locales'));

        $inRussian = $this->actingAs($this->editor(), 'cms')->getJson($this->api(), ['X-Webx-Locale' => 'ru'])->assertOk();
        $this->assertSame('Анна', $inRussian->json('data.1.name'));
    }

    #[Test]
    public function a_drag_writes_the_order_it_was_made_in(): void
    {
        $clinic = $this->category('Clinic');
        $a = $this->review('A', categories: [$clinic]);
        $b = $this->review('B', categories: [$clinic]);
        $c = $this->review('C');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$b->id, $a->id], 'category' => $clinic->id])
            ->assertNoContent();

        $inside = $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?category='.$clinic->id)->assertOk();
        $this->assertSame([$b->id, $a->id], array_column((array) $inside->json('data'), 'id'));

        $whole = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();
        $this->assertSame([$a->id, $b->id, $c->id], array_column((array) $whole->json('data'), 'id'), 'the order inside a category is its own');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertSame([$c->id, $a->id, $b->id], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api())->json('data'), 'id'));
        $this->assertSame([$b->id, $a->id], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?category='.$clinic->id)->json('data'), 'id'));
    }

    #[Test]
    public function a_new_review_is_created_from_the_values_of_its_form(): void
    {
        $clinic = $this->category('Clinic');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => [
                'name' => ['en' => 'Anna Petrova', 'ru' => 'Анна Петрова'],
                'job_title' => ['en' => 'CEO, Acme'],
                'text' => ['en' => "Great.\nWould come again.", 'ru' => ''],
                'rating' => 5,
                'reviewed_on' => '2026-09-20',
                'profile_url' => 'https://example.com/anna',
                'photo' => ['path' => 'media/ab/cd/anna.jpg', 'url' => 'https://cdn.example/anna.jpg'],
                'published' => true,
                'categories' => [$clinic->id],
            ],
        ])->assertCreated();

        $this->assertSame(['review', 'values'], array_keys((array) $response->json('data')));
        $this->assertSame(['id', 'name', 'published', 'deleted_at'], array_keys((array) $response->json('data.review')));
        $this->assertSame('Anna Petrova', $response->json('data.review.name'));
        $this->assertTrue($response->json('data.review.published'));

        $values = (array) $response->json('data.values');
        $this->assertSame(['en' => 'Anna Petrova', 'ru' => 'Анна Петрова'], $values['name']);
        $this->assertSame(['en' => 'CEO, Acme'], $values['job_title']);
        $this->assertSame(['en' => "Great.\nWould come again."], $values['text'], 'an emptied language is taken away');
        $this->assertSame(5, $values['rating']);
        $this->assertSame('2026-09-20', $values['reviewed_on']);
        $this->assertSame('https://example.com/anna', $values['profile_url']);
        $this->assertSame(['path' => 'media/ab/cd/anna.jpg'], $values['photo'], 'the address is never kept');
        $this->assertTrue($values['published']);
        $this->assertSame([$clinic->id], $values['categories']);

        $review = Review::query()->findOrFail($response->json('data.review.id'));
        $this->assertTrue($review->visibleIn('en'));
        $this->assertFalse($review->visibleIn('ru'));

        $this->actingAs($this->editor(), 'cms')->getJson($this->api($review->id))
            ->assertOk()
            ->assertJsonPath('data.review.id', $review->id)
            ->assertJsonPath('data.values.reviewed_on', '2026-09-20');
    }

    #[Test]
    public function a_profile_that_is_not_a_web_address_and_a_rating_out_of_range_are_refused_under_their_fields(): void
    {
        foreach (['javascript:alert(1)', 'ftp://example.com/anna', 'example.com/anna'] as $address) {
            $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
                'values' => ['name' => ['en' => 'Anna'], 'profile_url' => $address],
            ])->assertUnprocessable()->assertJsonValidationErrors(['profile_url']);
        }

        foreach ([6, -1, 2.5, 'five'] as $rating) {
            $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
                'values' => ['name' => ['en' => 'Anna'], 'rating' => $rating],
            ])->assertUnprocessable()->assertJsonValidationErrors(['rating']);
        }

        $this->assertSame(0, Review::query()->withTrashed()->count(), 'a refusal leaves nothing behind');

        // What the stars send when they are cleared is no rating, not a rating of nothing.
        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => ['name' => ['en' => 'Anna'], 'rating' => 0, 'profile_url' => ' http://example.com '],
        ])->assertCreated()
            ->assertJsonPath('data.values.rating', null)
            ->assertJsonPath('data.values.profile_url', 'http://example.com');
    }

    #[Test]
    public function a_refused_category_leaves_nothing_behind(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => ['name' => ['en' => 'Orphan'], 'categories' => [999]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['categories']);

        $this->assertSame(0, Review::query()->withTrashed()->count());
    }

    #[Test]
    public function a_save_lays_one_language_over_the_others(): void
    {
        $review = $this->review('Anna', 'Анна довольна.', attributes: ['rating' => 3, 'reviewed_on' => '2026-01-02']);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($review->id), [
            'values' => ['text' => ['en' => 'Anna loved it.'], 'published' => false, 'reviewed_on' => null],
        ])->assertOk()
            ->assertJsonPath('data.values.text', ['en' => 'Anna loved it.', 'ru' => 'Анна довольна.'])
            ->assertJsonPath('data.values.rating', 3)
            ->assertJsonPath('data.values.reviewed_on', null)
            ->assertJsonPath('data.values.published', false)
            ->assertJsonPath('data.review.published', false);
    }

    #[Test]
    public function a_field_of_the_project_goes_into_extra(): void
    {
        Screens::extend(Review::SCREEN, [[
            'op' => 'add',
            'target' => 'project-fields',
            'node' => ['id' => 'city', 'type' => 'wx-input', 'name' => 'city', 'label' => 'City'],
        ]]);

        $review = $this->review('Anna');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($review->id), ['values' => ['city' => 'Kyiv']])
            ->assertOk()
            ->assertJsonPath('data.values.city', 'Kyiv');

        $this->assertSame('Kyiv', $review->refresh()->extra('city'));
        $this->assertSame(['city' => 'Kyiv'], reviews()->first()['fields'] ?? null);
    }

    #[Test]
    public function the_bin_keeps_the_review_and_gives_it_back_as_a_row_of_the_list(): void
    {
        $clinic = $this->category('Clinic');
        $review = $this->review('Anna', categories: [$clinic]);

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api($review->id))->assertNoContent();
        $this->assertSame([], $this->actingAs($this->editor(), 'cms')->getJson($this->api())->json('data'));
        $this->assertSame([$review->id], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?trashed=1')->json('data'), 'id'));
        $this->assertNotNull($this->actingAs($this->editor(), 'cms')->getJson($this->api().'?trashed=1')->json('data.0.deleted_at'));

        $restored = $this->actingAs($this->editor(), 'cms')->postJson($this->api($review->id.'/restore'))->assertOk();

        $this->assertSame($review->id, $restored->json('data.id'));
        $this->assertSame('Anna', $restored->json('data.name'));
        $this->assertSame([['id' => $clinic->id, 'title' => 'Clinic']], $restored->json('data.categories'));
        $this->assertNull($restored->json('data.deleted_at'));
    }

    #[Test]
    public function a_category_has_no_address_and_holds_its_reviews(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/reviews/categories', ['title' => ['en' => 'Our clinic']])
            ->assertCreated();

        $category = ReviewCategory::query()->findOrFail($response->json('data.id'));
        $this->assertNull($category->getRawOriginal('slug'));

        $this->review('Anna', categories: [$category]);

        $this->actingAs($this->editor(), 'cms')->deleteJson('/api/cms/reviews/categories/'.$category->id)->assertUnprocessable();
    }
}
