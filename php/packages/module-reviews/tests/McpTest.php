<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;

/**
 * Reviews by their other doors (§4.8): the same list, the same screen, the same order code.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function both_sections_offer_their_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['reviews_list', 'reviews_get', 'reviews_create', 'reviews_update', 'reviews_delete', 'reviews_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('reviews')),
        );

        $this->assertSame(
            ['review_categories_list', 'review_categories_create', 'review_categories_update', 'review_categories_delete', 'review_categories_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('review-categories')),
        );

        $this->assertSame(['reviews.view', 'reviews.manage'], $registry->tool('reviews_list')->permissions());
        $this->assertSame(['reviews.manage'], $registry->tool('reviews_reorder')->permissions());
        $this->assertSame(['reviews.categories.manage'], $registry->tool('review_categories_create')->permissions());

        $this->assertContains('reviews://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function categories_have_no_address_and_are_named_by_title(): void
    {
        $create = $this->app->make(ToolRegistry::class)->tool('review_categories_create');

        $this->assertArrayNotHasKey('slug', $create->tool->inputSchema['properties'] ?? []);

        $this->category('Clinic');

        $this->agent('review_categories_update', ['category' => 'clinic', 'values' => ['is_visible' => false]])->assertOk();
        $this->assertFalse(ReviewCategory::query()->firstOrFail()->is_visible);
        $this->assertNull(ReviewCategory::query()->firstOrFail()->slug);
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['reviews.view']);

        $this->agent('reviews_list', [], $reader)->assertOk();
        $this->agent('reviews_create', ['name' => 'Nobody'], $reader)->assertHasErrors(['[reviews.manage]']);
    }

    #[Test]
    public function the_list_says_where_a_reader_sees_each_review(): void
    {
        $this->review('Anna', 'Отлично.');
        $this->review('Mark');
        $this->review('Hidden', 'Скрыт.', published: false);

        $rows = $this->content($this->agent('reviews_list'))['reviews'];

        $this->assertSame(['en', 'ru'], $rows[0]['visible_in']);
        $this->assertSame(['en'], $rows[1]['visible_in']);
        $this->assertSame([], $rows[2]['visible_in']);
        $this->assertSame(['en', 'ru'], $rows[2]['written_in']);
    }

    #[Test]
    public function create_writes_through_the_screen(): void
    {
        $clinic = $this->category('Clinic');
        $this->picture('media/ab/cd/anna.jpg');

        $created = $this->content($this->agent('reviews_create', [
            'name' => 'Anna Petrova',
            'job_title' => ['en' => 'Owner', 'ru' => 'Владелец'],
            'text' => 'Five weeks to launch.',
            'rating' => 5,
            'reviewed_on' => '2026-09-20',
            'profile_url' => 'https://example.com/anna',
            'photo' => 'media/ab/cd/anna.jpg',
            'categories' => ['Clinic'],
        ]));

        $this->assertFalse($created['values']['published']);
        $this->assertSame([$clinic->id], $created['values']['categories']);
        // A plain string is the default language, not the language of the request.
        $this->assertSame(['en' => 'Anna Petrova'], $created['values']['name']);
        $this->assertSame(['en' => 'Five weeks to launch.'], $created['values']['text']);
        $this->assertSame(5, $created['values']['rating']);
        $this->assertSame('2026-09-20', $created['values']['reviewed_on']);
        $this->assertSame('media/ab/cd/anna.jpg', $created['review']['photo']);
        $this->assertSame([], $created['review']['visible_in']);

        $id = $created['review']['id'];

        $this->agent('reviews_update', ['review' => $id, 'values' => ['published' => true, 'text' => ['ru' => 'Пять недель до запуска.'], 'rating' => 0]])->assertOk();

        $review = Review::query()->findOrFail($id);
        $this->assertTrue($review->visibleIn('ru'));
        $this->assertTrue($review->visibleIn('en'));
        // Cleared stars are no rating, as in the panel.
        $this->assertNull($review->rating);
    }

    #[Test]
    public function a_refused_create_leaves_no_review_behind(): void
    {
        // A field of the project refusing its value: the review named in the same call is not left
        // behind without it.
        Screens::extend('reviews.form', [['op' => 'add', 'target' => 'project-fields', 'node' => [
            'id' => 'weight', 'type' => 'wx-input-number', 'name' => 'weight', 'label' => 'Weight', 'props' => ['min' => 10],
        ]]]);

        $this->agent('reviews_create', ['name' => 'Half written', 'values' => ['weight' => 5]])->assertHasErrors(['weight']);
        $this->agent('reviews_create', ['name' => 'Six stars', 'rating' => 6])->assertHasErrors(['rating']);
        $this->agent('reviews_create', ['name' => 'Script', 'profile_url' => 'javascript:alert(1)'])->assertHasErrors(['profile_url']);
        $this->agent('reviews_create', ['name' => 'Filed', 'categories' => [999]])->assertHasErrors(['No such category']);
        $this->agent('reviews_create', ['name' => 'Pictured', 'photo' => 'media/no/such.jpg'])->assertHasErrors(['no file']);

        $this->assertSame(0, Review::withTrashed()->count());
    }

    #[Test]
    public function update_keeps_the_languages_it_was_not_given(): void
    {
        $review = $this->review('Anna', 'Отлично.');

        $this->agent('reviews_update', ['review' => $review->id, 'values' => ['text' => 'Great.']])->assertOk();

        $this->assertSame(['en' => 'Great.', 'ru' => 'Отлично.'], $review->refresh()->getTranslations('text'));

        $this->agent('reviews_update', ['review' => $review->id, 'values' => ['photo' => null, 'text' => ['ru' => '']]])->assertOk();
        $this->assertSame(['en' => 'Great.'], $review->refresh()->getTranslations('text'));
    }

    #[Test]
    public function reordering_a_category_is_its_own_order_and_refuses_a_stranger(): void
    {
        $clinic = $this->category('Clinic');
        $first = $this->review('First', categories: [$clinic]);
        $second = $this->review('Second', categories: [$clinic]);
        $stranger = $this->review('Stranger');

        $this->agent('reviews_reorder', ['reviews' => [$stranger->id], 'category' => $clinic->id])->assertHasErrors(['Not in this category']);

        $listed = $this->content($this->agent('reviews_reorder', ['reviews' => [$second->id, $first->id], 'category' => 'Clinic']));

        $this->assertSame([$second->id, $first->id], array_column($listed['reviews'], 'id'));
        // The whole list keeps its own order.
        $this->assertSame(
            [$first->id, $second->id, $stranger->id],
            array_column($this->content($this->agent('reviews_list'))['reviews'], 'id'),
        );
    }

    #[Test]
    public function a_review_is_named_by_its_id_only(): void
    {
        $this->review('Anna');

        $this->agent('reviews_get', ['review' => 'Anna'])->assertHasErrors(['its id']);
        $this->agent('reviews_get', ['review' => 999])->assertHasErrors(['[999]']);
    }

    #[Test]
    public function delete_puts_it_in_the_bin(): void
    {
        $review = $this->review('Gone');

        $this->agent('reviews_delete', ['review' => $review->id, 'dry_run' => true])->assertOk();
        $this->assertFalse($review->refresh()->trashed());

        $this->agent('reviews_delete', ['review' => (string) $review->id])->assertOk();

        $this->assertTrue($review->refresh()->trashed());
        $this->assertSame([$review->id], array_column($this->content($this->agent('reviews_list', ['trashed' => true]))['reviews'], 'id'));
        $this->agent('reviews_update', ['review' => $review->id, 'values' => ['published' => false]])->assertHasErrors(['in the bin']);
    }

    #[Test]
    public function the_catalogue_lists_categories_with_their_reviews_and_the_rest_at_the_end(): void
    {
        $clinic = $this->category('Clinic');
        $hidden = $this->category('Home page', visible: false);
        $both = $this->review('Both', 'Оба.', categories: [$clinic, $hidden]);
        $draft = $this->review('Draft', published: false, categories: [$clinic]);
        $loose = $this->review('Loose');

        $catalog = ($this->resource('reviews://catalog')->handler)();

        $this->assertSame(['Clinic', 'Home page'], array_column($catalog['categories'], 'title'));
        $this->assertFalse($catalog['categories'][1]['visible']);
        $this->assertSame([$both->id, $draft->id], array_column($catalog['categories'][0]['reviews'], 'id'));
        $this->assertSame([$both->id], array_column($catalog['categories'][1]['reviews'], 'id'));
        $this->assertFalse($catalog['categories'][0]['reviews'][1]['published']);
        $this->assertSame([], $catalog['categories'][0]['reviews'][1]['visible_in']);
        $this->assertSame(['en'], $catalog['categories'][0]['reviews'][1]['written_in']);
        $this->assertSame(['en', 'ru'], $catalog['categories'][0]['reviews'][0]['visible_in']);
        $this->assertSame('Both', $catalog['categories'][0]['reviews'][0]['name']);
        $this->assertSame([$loose->id], array_column($catalog['uncategorised'], 'id'));
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
