<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Reviews\Models\ReviewCategory;

/**
 * The shared category tools, as an agent meets them on a kind named by its title: one name is one
 * category, a dry run is the write rolled back, and nothing unknown is quietly dropped.
 */
final class CategoryToolsTest extends TestCase
{
    #[Test]
    public function a_second_category_by_one_name_is_refused_in_any_case(): void
    {
        $clinic = $this->category('Work with the clinic');

        $this->agent('review_categories_create', ['title' => 'work WITH the clinic'])
            ->assertHasErrors(['already exists: #'.$clinic->id]);
        $this->agent('review_categories_create', ['title' => 'Work with the clinic', 'dry_run' => true])
            ->assertHasErrors(['already exists']);

        $other = $this->category('Other');

        $this->agent('review_categories_update', ['category' => $other->id, 'values' => ['title' => ['en' => 'Work with the clinic']]])
            ->assertHasErrors(['already exists']);
        // Its own name is not a clash.
        $this->agent('review_categories_update', ['category' => $clinic->id, 'values' => ['title' => ['en' => 'Work with the clinic']]])
            ->assertOk();

        $this->assertSame(2, ReviewCategory::query()->count());
    }

    #[Test]
    public function a_name_two_already_answer_to_is_refused_with_both_ids(): void
    {
        // Made before the tools refused it: what a site that has the pair already holds.
        $empty = $this->category('Clinic');
        $full = $this->category('Clinic');

        $this->agent('review_categories_get', ['category' => 'clinic'])
            ->assertHasErrors(["#{$empty->id}, #{$full->id}"]);
        $this->agent('reviews_list', ['category' => 'Clinic'])
            ->assertHasErrors(["#{$empty->id}, #{$full->id}"]);

        $this->agent('review_categories_get', ['category' => $full->id])->assertOk();
    }

    #[Test]
    public function get_answers_what_the_list_says_and_every_value(): void
    {
        $clinic = $this->category('Clinic');

        $got = $this->content($this->agent('review_categories_get', ['category' => 'Clinic']));

        $this->assertSame($clinic->id, $got['category']['id']);
        $this->assertSame(['en' => 'Clinic'], $got['values']['title']);
    }

    #[Test]
    public function reorder_refuses_an_unknown_id_and_its_dry_run_is_the_order_it_would_make(): void
    {
        $first = $this->category('First');
        $second = $this->category('Second');

        $this->agent('review_categories_reorder', ['ids' => [$second->id, 99]])->assertHasErrors(['#99']);

        $dry = $this->content($this->agent('review_categories_reorder', ['ids' => [$second->id, $first->id], 'dry_run' => true]));

        $this->assertTrue($dry['dry_run']);
        $this->assertSame([$second->id, $first->id], array_column($dry['categories'], 'id'));
        // Nothing moved.
        $this->assertSame(
            [$first->id, $second->id],
            ReviewCategory::query()->orderBy('position')->pluck('id')->all(),
        );
    }

    #[Test]
    public function unknown_arguments_and_fields_are_refused_rather_than_dropped(): void
    {
        $clinic = $this->category('Clinic');

        $this->agent('review_categories_create', ['title' => 'New', 'colour' => 'red'])
            ->assertHasErrors(['review_categories_create has no argument [colour]']);
        $this->agent('review_categories_update', ['category' => $clinic->id, 'values' => ['colour' => 'red']])
            ->assertHasErrors(['review_categories_update has no field [colour]']);

        $this->assertSame(1, ReviewCategory::query()->count());
    }

    #[Test]
    public function the_dry_run_of_create_answers_what_create_would_and_keeps_nothing(): void
    {
        $dry = $this->content($this->agent('review_categories_create', ['title' => 'Clinic', 'dry_run' => true]));

        $this->assertTrue($dry['dry_run']);
        $this->assertNull($dry['category']['id']);
        $this->assertSame(['en' => 'Clinic'], $dry['category']['title']);
        $this->assertSame(0, ReviewCategory::query()->count());
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
