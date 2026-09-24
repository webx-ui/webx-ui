<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * The demo catalogue (§4.12), and the one thing about it that is the point: a service in two
 * categories, standing in a different place in each.
 */
final class DemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_three_categories_and_eight_published_services_with_covers_and_blocks(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(3, ServiceCategory::query()->count());
        $this->assertSame(8, Service::query()->visible()->count());

        foreach (Service::query()->get() as $service) {
            $this->assertInstanceOf(MediaFile::class, $service->cover, "{$service->id} has a cover.");
            $this->assertNotSame([], $service->blocksTree());
        }
    }

    #[Test]
    public function one_service_stands_last_in_one_category_and_first_in_another(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $maintenance = Service::query()->where('slug->en', 'site-maintenance')->firstOrFail();
        $websites = ServiceCategory::query()->where('slug->en', 'websites')->firstOrFail();
        $support = ServiceCategory::query()->where('slug->en', 'support')->firstOrFail();

        $inWebsites = Service::query()->orderedIn((int) $websites->getKey())->pluck('id')->all();
        $inSupport = Service::query()->orderedIn((int) $support->getKey())->pluck('id')->all();

        $this->assertSame($maintenance->getKey(), end($inWebsites));
        $this->assertSame($maintenance->getKey(), $inSupport[0]);
        // Filed under websites first, so that is the main one — the breadcrumbs go through it.
        $this->assertSame($websites->getKey(), $maintenance->mainServiceCategory()?->getKey());

        $this->get('/services/support')->assertOk()->assertSeeInOrder(['Site maintenance', 'SEO audit', 'Content editing']);
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Service::withTrashed()->count());
        $this->assertSame(0, ServiceCategory::withTrashed()->count());
        $this->assertSame(0, MediaFile::query()->count());
    }
}
