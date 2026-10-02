<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLandings\Catalog\LandingGenerator;
use WebxUi\CatalogLandings\Jobs\GenerateLandings;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogLandings\Models\LandingRun;

/**
 * §8.3 of the landings spec: «Create in bulk» — templates, the preview with its conflicts, the
 * conflicting rows skipped and the rest made, `dry_run` writing nothing; and the section of the
 * panel it lives in (§8.1, §8.2).
 */
final class GenerateTest extends TestCase
{
    private Category $laptops;

    private Brand $apple;

    private Brand $dell;

    private Brand $asus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laptops = $this->category('laptops');
        $this->apple = $this->brand('Apple');
        $this->dell = $this->brand('Dell');
        $this->asus = $this->brand('Asus');
        $this->product('MacBook Air', $this->laptops, $this->apple);
        $this->product('MacBook Pro', $this->laptops, $this->apple);
        $this->product('XPS', $this->laptops, $this->dell);
    }

    #[Test]
    public function the_preview_fills_the_templates_marks_the_conflicts_and_writes_nothing(): void
    {
        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])], ['name' => 'Dell laptops']);

        $rows = $this->actingAs($this->editor(), 'cms')->postJson($this->api('generate'), [
            'categories' => [$this->laptops->id],
            'facet' => 'brand',
            'values' => [(string) $this->apple->id, (string) $this->dell->id, (string) $this->asus->id],
            'h1' => 'Buy {category} {value}',
            'dry_run' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.free', 1)
            ->json('data.rows');

        $this->assertSame(['en' => 'laptops-apple'], $rows[0]['slug']);
        $this->assertSame(['en' => 'Laptops Apple'], $rows[0]['name']);
        $this->assertSame(['en' => 'Buy Laptops Apple'], $rows[0]['h1']);
        $this->assertSame(2, $rows[0]['count']);
        $this->assertNull($rows[0]['conflict']);
        $this->assertSame(LandingGenerator::SET_TAKEN, $rows[1]['conflict']);
        $this->assertSame('The set is already the landing «Dell laptops».', $rows[1]['message']);
        $this->assertSame(LandingGenerator::EMPTY, $rows[2]['conflict']);

        $this->assertSame(1, Landing::query()->count());
    }

    #[Test]
    public function every_value_with_enough_products_and_a_taken_address_is_skipped(): void
    {
        // A category holds the address the template gives the Dell landing.
        $this->category('laptops-dell');

        $rows = $this->actingAs($this->editor(), 'cms')->postJson($this->api('generate'), [
            'categories' => [$this->laptops->id],
            'facet' => 'brand',
            'dry_run' => true,
        ])->assertOk()->json('data.rows');

        // Asus has no products and is not offered; Dell's address is a category's.
        $this->assertSame([(string) $this->apple->id, (string) $this->dell->id], array_column($rows, 'value'));
        $this->assertSame(LandingGenerator::SLUG_TAKEN, $rows[1]['conflict']);

        $only = $this->actingAs($this->editor(), 'cms')->postJson($this->api('generate'), [
            'categories' => [$this->laptops->id],
            'facet' => 'brand',
            'min_products' => 2,
            'dry_run' => true,
        ])->json('data.rows');

        $this->assertSame([(string) $this->apple->id], array_column($only, 'value'));
    }

    #[Test]
    public function a_run_makes_the_free_rows_with_their_seo_and_skips_the_rest(): void
    {
        $this->landing('laptops-dell', $this->laptops, ['brand' => $this->brands([$this->dell])]);

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('generate'), [
            'categories' => [$this->laptops->id],
            'facet' => 'brand',
            'values' => [$this->apple->id, $this->dell->id],
            'title' => '{value} — {category}',
            'publish' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', null)
            ->assertJsonPath('data.status', LandingRun::DONE)
            ->assertJsonPath('data.done', 1)
            ->assertJsonPath('data.skipped', 1);

        $made = Landing::query()->where('slug->en', 'laptops-apple')->firstOrFail();

        $this->assertTrue($made->is_published);
        $this->assertSame(2, $made->products_count);
        $this->assertSame('Apple — Laptops', $made->seoValue()['title']['en'] ?? null);
        $this->get('/laptops-apple')->assertOk();
    }

    #[Test]
    public function a_large_run_goes_to_the_queue_and_is_polled(): void
    {
        config(['webx-catalog-landings.generate.sync_limit' => 0, 'webx-catalog-landings.generate.chunk' => 1]);
        $hp = $this->brand('HP');
        $this->product('Envy', $this->laptops, $hp);

        Queue::fake();

        $id = $this->actingAs($this->editor(), 'cms')->postJson($this->api('generate'), [
            'categories' => [$this->laptops->id],
            'facet' => 'brand',
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', LandingRun::QUEUED)
            ->assertJsonPath('data.total', 3)
            ->json('data.id');

        Queue::assertPushed(GenerateLandings::class);
        $this->assertSame(0, Landing::query()->count());

        $generator = app(LandingGenerator::class);
        $this->assertTrue($generator->chunk($id));
        $this->assertTrue($generator->chunk($id));
        $this->assertFalse($generator->chunk($id));

        $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson($this->api("generate/{$id}"))
            ->assertOk()
            ->assertJsonPath('data.status', LandingRun::DONE)
            ->assertJsonPath('data.done', 3);
        $this->assertSame(3, Landing::query()->count());
    }

    #[Test]
    public function a_facet_of_numbers_or_a_template_without_the_value_is_refused(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api('generate'), [
            'categories' => [$this->laptops->id],
            'facet' => 'price',
        ])->assertUnprocessable()->assertJsonValidationErrors('facet');

        $this->actingAs($editor, 'cms')->postJson($this->api('generate'), [
            'categories' => [$this->laptops->id],
            'facet' => 'brand',
            'slug' => '{category}-sale',
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');

        $this->actingAs($this->editor(['catalog.view']), 'cms')->postJson($this->api('generate'), [
            'categories' => [$this->laptops->id],
            'facet' => 'brand',
            'dry_run' => true,
        ])->assertForbidden();
    }

    #[Test]
    public function the_count_suggests_the_address_of_the_set(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api('count'), [
            'category_id' => $this->laptops->id,
            'filters' => ['brand' => ['values' => [$this->apple->id]]],
        ])->assertOk()->assertJsonPath('data.count', 2)->assertJsonPath('data.suggested.en', 'laptops-apple');
    }

    #[Test]
    public function the_section_and_its_form_are_registered(): void
    {
        $module = app(ModuleRegistry::class)->get('catalog-landings');

        $this->assertNotNull($module);
        $this->assertSame('catalog', $module->group());
        $this->assertSame('Landings', $module->title());
        $this->assertTrue(app(ScreenRegistry::class)->has(Landing::SCREEN));
    }
}
