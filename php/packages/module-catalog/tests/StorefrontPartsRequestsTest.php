<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\Catalog\Tests\Fixtures\RecordingPart;

/**
 * What the parts prepared belongs to one page, and a worker serves many from one process: a page
 * without a listing calls `extend()` alone, and must not add to the page served before it.
 */
final class StorefrontPartsRequestsTest extends TestCase
{
    private Category $laptops;

    private RecordingPart $part;

    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('fixture', __DIR__.'/Fixtures/views');

        $this->laptops = $this->category('laptops');
        $this->part = new RecordingPart;

        $this->app->make(StorefrontParts::class)->register($this->part);
    }

    #[Test]
    public function a_page_without_a_listing_starts_from_nothing_after_a_page_with_one(): void
    {
        $one = $this->product('One', $this->laptops);
        $two = $this->product('Two', $this->laptops);

        // A page that shows one product and calls `extend()`, as a shelf outside a listing does.
        Route::get('/strip/{product}', function (int $product): string {
            $parts = $this->app->make(StorefrontParts::class);
            $shown = Product::query()->findOrFail($product);
            $parts->extend(new Collection([$shown]));

            return $parts->render('catalog.card.badges', ['product' => $shown]);
        });

        $this->get('/laptops')->assertOk();
        $this->assertCount(1, $this->part->asked);

        // Prepared by the page before, and still asked about on this one.
        $this->get("/strip/{$one->id}")->assertOk()->assertSee('Sale');
        $this->assertSame([(int) $one->id], $this->part->asked[1]);

        // And the page after it is told about its own product alone.
        $this->get("/strip/{$two->id}")->assertOk()->assertSee('Sale');
        $this->assertSame([(int) $two->id], $this->part->asked[2]);
    }

    #[Test]
    public function the_parts_themselves_survive_the_end_of_a_request(): void
    {
        $this->product('One', $this->laptops);

        $this->get('/laptops')->assertOk();
        $this->get('/laptops')->assertOk();

        $this->assertContains($this->part, $this->app->make(StorefrontParts::class)->at('catalog.card.badges'));
        $this->assertCount(2, $this->part->asked);
    }

    #[Test]
    public function a_job_starts_from_nothing_too(): void
    {
        $product = $this->product('One', $this->laptops);
        $parts = $this->app->make(StorefrontParts::class);

        $parts->prepare(new Collection([$product]));
        $this->app['events']->dispatch(new JobProcessing('sync', $this->createStub(Job::class)));
        $parts->extend(new Collection([$product]));

        $this->assertCount(2, $this->part->asked);
    }
}
