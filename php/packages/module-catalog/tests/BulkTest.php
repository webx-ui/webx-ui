<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Bulk\Actions\CoreAction;
use WebxUi\Catalog\Bulk\BulkActions;
use WebxUi\Catalog\Bulk\BulkRunner;
use WebxUi\Catalog\Bulk\ProcessBulkChunk;
use WebxUi\Catalog\Models\BulkRun;
use WebxUi\Catalog\Models\Product;

/**
 * Bulk actions (§11.4, §16): small ones inside the request, large ones queued in chunks; the ids
 * fixed when the run starts; a product that refuses does not take its neighbours down; one run in
 * the journal with a row per product; each action behind its own permission.
 */
final class BulkTest extends TestCase
{
    #[Test]
    public function a_small_selection_is_done_at_once_and_a_refusal_stays_beside_its_product(): void
    {
        $shoes = $this->category('shoes');
        $a = $this->product('Boot', $shoes, ['is_published' => false]);
        $b = $this->product('Sandal', $shoes, ['is_published' => false]);
        $orphan = $this->product('Orphan');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('bulk'), ['action' => 'publish', 'selection' => ['ids' => [$a->id, $b->id, $orphan->id]]])
            ->assertOk()
            ->assertJsonPath('data.id', null)
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.done', 2)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.errors.0.id', $orphan->id)
            ->assertJsonPath('data.errors.0.name', 'Orphan');

        $this->assertTrue($a->refresh()->is_published);
        $this->assertTrue($b->refresh()->is_published);
        $this->assertFalse($orphan->refresh()->is_published);

        $run = HistoryEntry::query()->where('event', HistoryEntry::RUN)->sole();
        $this->assertSame('bulk', $run->source);
        $this->assertSame('publish', $run->summary['action'] ?? null);
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            HistoryEntry::query()->where('parent_id', $run->id)->where('event', HistoryEntry::PUBLISHED)->pluck('subject_id')->all(),
        );
    }

    #[Test]
    public function a_query_is_turned_into_ids_when_the_run_starts_and_queued_in_chunks(): void
    {
        config(['webx-catalog.bulk.sync_limit' => 2, 'webx-catalog.bulk.chunk' => 2]);
        Queue::fake();

        $shoes = $this->category('shoes');
        $belts = $this->category('belts');
        $ids = [];

        foreach (['One', 'Two', 'Three', 'Four', 'Five'] as $name) {
            $ids[] = $this->product($name, $shoes, ['is_published' => false])->id;
        }

        $this->product('Published', $shoes);

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('bulk'), [
                'action' => 'set-category',
                'params' => ['category_id' => $belts->id],
                'selection' => ['query' => ['state' => 'unpublished']],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.total', 5);

        Queue::assertPushed(ProcessBulkChunk::class, 1);

        // Matches the filter now, but did not when the run started.
        $late = $this->product('Late', $shoes, ['is_published' => false]);

        $runner = $this->app->make(BulkRunner::class);
        $runId = (int) $response->json('data.id');
        $chunks = 0;

        while ($runner->chunk($runId)) {
            $chunks++;
        }

        $this->assertSame(2, $chunks, 'Five products in chunks of two: two full chunks, then the last one ends the run.');
        $this->assertSame([$belts->id], Product::query()->whereKey($ids)->distinct()->pluck('category_id')->all());
        $this->assertSame($shoes->id, $late->refresh()->category_id);

        $this->actingAs($this->editor(), 'cms')->getJson($this->api("bulk/{$runId}"))
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.done', 5)
            ->assertJsonPath('data.failed', 0);

        $run = HistoryEntry::query()->where('event', HistoryEntry::RUN)->sole();
        $this->assertSame(5, $run->summary['rows'] ?? null);
        $this->assertSame(5, HistoryEntry::query()->where('parent_id', $run->id)->count());
        $this->assertSame(['bulk'], HistoryEntry::query()->where('parent_id', $run->id)->distinct()->pluck('source')->all());
        $this->assertNotNull(HistoryEntry::query()->where('parent_id', $run->id)->value('admin_id'), 'The chunk writes as the administrator who started the run.');
    }

    #[Test]
    public function a_chunk_run_again_does_nothing_twice(): void
    {
        config(['webx-catalog.bulk.sync_limit' => 0, 'webx-catalog.bulk.chunk' => 10]);
        Queue::fake();

        $shoes = $this->category('shoes');
        $product = $this->product('Boot', $shoes);
        $belts = $this->category('belts');

        $run = $this->app->make(BulkRunner::class)->start('add-category', ['category_id' => $belts->id], ['ids' => [$product->id]], null);
        $runner = $this->app->make(BulkRunner::class);

        $this->assertFalse($runner->chunk($run->id));
        $this->assertFalse($runner->chunk($run->id));

        $run->refresh();
        $this->assertSame(1, $run->done);
        $this->assertSame(BulkRun::DONE, $run->status);
        $this->assertSame(1, HistoryEntry::query()->where('parent_id', $run->history_id)->count());
    }

    #[Test]
    public function an_exception_in_one_product_does_not_roll_back_its_neighbours(): void
    {
        $this->app->make(BulkActions::class)->register(new class extends CoreAction
        {
            public function key(): string
            {
                return 'rename';
            }

            public function apply(Product $product, array $params): array
            {
                $product->setTranslation('name', 'en', $product->displayName().'!');
                $product->save();

                if ($product->sku === 'BAD') {
                    throw new \RuntimeException('Nope.');
                }

                return $product->takeHistoryChanges();
            }
        });

        $good = $this->product('Good');
        $bad = $this->product('Bad', null, ['sku' => 'BAD']);

        $run = $this->app->make(BulkRunner::class)->start('rename', [], ['ids' => [$good->id, $bad->id]], null);

        $this->assertSame(1, $run->done);
        $this->assertSame(1, $run->failed);
        $this->assertSame('Nope.', $run->errors[0]['message'] ?? null);
        $this->assertSame('Good!', $good->refresh()->displayName());
        $this->assertSame('Bad', $bad->refresh()->displayName(), 'The savepoint took the failed product back.');
    }

    #[Test]
    public function each_action_is_behind_its_own_permission(): void
    {
        $product = $this->product('Boot');
        $manager = $this->editor(['catalog.view', 'catalog.manage']);
        $deleter = $this->editor(['catalog.view', 'catalog.delete']);

        $keys = array_column((array) $this->actingAs($manager, 'cms')->getJson($this->api('bulk'))->assertOk()->json('data'), 'key');
        $this->assertContains('publish', $keys);
        $this->assertNotContains('delete', $keys);

        $this->actingAs($manager, 'cms')
            ->postJson($this->api('bulk'), ['action' => 'delete', 'selection' => ['ids' => [$product->id]]])
            ->assertForbidden();
        $this->assertNotSoftDeleted($product);

        $this->actingAs($deleter, 'cms')
            ->postJson($this->api('bulk'), ['action' => 'delete', 'selection' => ['ids' => [$product->id]]])
            ->assertOk()
            ->assertJsonPath('data.done', 1);
        $this->assertSoftDeleted($product);

        // A restore looks in «Deleted», not among the live products.
        $this->actingAs($deleter, 'cms')
            ->postJson($this->api('bulk'), ['action' => 'restore', 'selection' => ['ids' => [$product->id]]])
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.done', 1);
        $this->assertNotSoftDeleted($product);

        $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson($this->api('bulk'))->assertForbidden();
    }

    #[Test]
    public function an_unknown_action_and_bad_params_are_refused_before_anything_runs(): void
    {
        $editor = $this->editor();
        $product = $this->product('Boot');

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('bulk'), ['action' => 'melt', 'selection' => ['ids' => [$product->id]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('bulk'), ['action' => 'set-category', 'params' => ['category_id' => 999], 'selection' => ['ids' => [$product->id]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $this->assertSame(0, HistoryEntry::query()->count() - HistoryEntry::query()->where('event', HistoryEntry::CREATED)->count());
    }
}
