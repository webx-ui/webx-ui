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
use WebxUi\Reviews\Models\Review;

/**
 * A dry run is the write, rolled back: refused where the write is refused. And what the tools do
 * not take is refused, not dropped; the bin can be emptied and undone by an agent too.
 */
final class McpDryRunTest extends TestCase
{
    #[Test]
    public function a_dry_run_is_refused_where_the_write_is(): void
    {
        $this->agent('reviews_create', ['name' => 'Anna', 'rating' => 7, 'dry_run' => true])->assertHasErrors(['rating']);
        $this->agent('reviews_create', ['name' => 'Anna', 'rating' => 7])->assertHasErrors(['rating']);

        $review = $this->review('Anna');

        $this->agent('reviews_update', ['review' => $review->id, 'values' => ['rating' => 7], 'dry_run' => true])->assertHasErrors(['rating']);

        $dry = $this->content($this->agent('reviews_create', ['name' => 'Boris', 'rating' => 5, 'dry_run' => true]));

        $this->assertTrue($dry['dry_run']);
        $this->assertNull($dry['review']['id']);
        $this->assertSame(5, $dry['values']['rating']);
        $this->assertSame(1, Review::query()->count());
    }

    #[Test]
    public function the_dry_run_of_reorder_is_the_order_it_would_make(): void
    {
        $first = $this->review('First');
        $second = $this->review('Second');

        $dry = $this->content($this->agent('reviews_reorder', ['reviews' => [$second->id, $first->id], 'dry_run' => true]));

        $this->assertSame([$second->id, $first->id], array_column($dry['reviews'], 'id'));
        $this->assertSame([$first->id, $second->id], Review::query()->orderBy('position')->pluck('id')->all());
    }

    #[Test]
    public function unknown_arguments_and_fields_are_refused(): void
    {
        $review = $this->review('Anna');

        $this->agent('reviews_create', ['name' => 'Boris', 'stars' => 5])->assertHasErrors(['reviews_create has no argument [stars]']);
        $this->agent('reviews_create', ['name' => 'Boris', 'values' => ['stars' => 5]])->assertHasErrors(['reviews_create has no field [stars]']);
        $this->agent('reviews_update', ['review' => $review->id, 'values' => ['stars' => 5]])->assertHasErrors(['reviews_update has no field [stars]']);

        $this->assertSame(1, Review::query()->count());
    }

    #[Test]
    public function a_review_in_the_bin_is_restored_or_purged(): void
    {
        $kept = $this->review('Kept');
        $gone = $this->review('Gone');

        $this->agent('reviews_restore', ['review' => $kept->id])->assertHasErrors(['not in the bin']);
        $this->agent('reviews_purge', ['review' => $kept->id])->assertHasErrors(['not in the bin']);

        $kept->delete();
        $gone->delete();

        $this->agent('reviews_restore', ['review' => $kept->id, 'dry_run' => true])->assertOk();
        $this->assertTrue($kept->refresh()->trashed());

        $this->agent('reviews_restore', ['review' => $kept->id])->assertOk();
        $this->assertFalse($kept->refresh()->trashed());

        $this->agent('reviews_purge', ['review' => $gone->id, 'dry_run' => true])->assertOk();
        $this->assertNotNull(Review::withTrashed()->find($gone->id));

        $this->agent('reviews_purge', ['review' => $gone->id])->assertOk();
        $this->assertNull(Review::withTrashed()->find($gone->id));
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
