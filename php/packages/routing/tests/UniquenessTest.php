<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Tests\Fixtures\Category;
use WebxUi\Routing\Tests\Fixtures\Page;
use WebxUi\Routing\Tests\Fixtures\Product;

class UniquenessTest extends TestCase
{
    #[Test]
    public function fail_refuses_the_save_with_an_error_on_the_slug(): void
    {
        $this->page('about');

        try {
            $this->page('about');
            $this->fail('A second page on the same address should not have been saved.');
        } catch (PathRejected $rejected) {
            $this->assertSame('about', $rejected->path);
            $this->assertArrayHasKey('slug', $rejected->errors());
        }

        // And the refused page is not left behind without an address.
        $this->assertSame(1, Page::query()->count());
        $this->assertSame(1, Route::query()->count());
    }

    #[Test]
    public function suffix_picks_the_next_free_address(): void
    {
        Category::query()->create(['name' => 'Belts', 'slug' => 'remni']);
        $second = Category::query()->create(['name' => 'Belts', 'slug' => 'remni']);
        $third = Category::query()->create(['name' => 'Belts', 'slug' => 'remni']);

        $this->assertSame('remni-2', $second->routeCanonical()?->path);
        $this->assertSame('remni-3', $third->routeCanonical()?->path);
    }

    #[Test]
    public function the_suffix_goes_back_into_the_entity(): void
    {
        Category::query()->create(['name' => 'Belts', 'slug' => 'remni']);
        $second = Category::query()->create(['name' => 'Belts', 'slug' => 'remni']);

        // In memory and in the table: the form has to show the address the site will serve.
        $this->assertSame('remni-2', $second->slug);
        $this->assertSame('remni-2', Category::query()->findOrFail($second->getKey())->slug);
    }

    #[Test]
    public function a_race_lost_on_the_unique_index_is_recomputed(): void
    {
        Category::query()->create(['name' => 'Belts', 'slug' => 'remni']);

        // Another writer takes `remni-2` in the moment between our lookup and our insert: the
        // lookup is a fast path, the index is the guarantee. Note that the intruder is rolled
        // back with our own transaction here, so the address is free again by the time the
        // retry runs — in production the writer that won has committed and the retry moves on
        // to `remni-3`. What this test pins down is that losing means recomputing, not failing.
        $attempts = 0;

        Route::creating(function (Route $route) use (&$attempts): bool {
            if ($route->path !== 'remni-2') {
                return true;
            }

            $attempts++;

            if ($attempts === 1) {
                DB::table('routes')->insert([
                    'locale' => 'en',
                    'path' => 'remni-2',
                    'kind' => Route::CANONICAL,
                    'entity_type' => 'category',
                    'entity_id' => 9999,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return true;
        });

        $second = Category::query()->create(['name' => 'Belts', 'slug' => 'remni']);

        $this->assertSame(2, $attempts, 'the insert should have been attempted again');
        $this->assertSame('remni-2', $second->routeCanonical()?->path);
        $this->assertSame(2, Route::query()->count());
    }

    #[Test]
    public function an_address_longer_than_the_column_is_a_validation_error(): void
    {
        $this->expectException(PathRejected::class);

        Product::query()->create([
            'name' => 'Belt',
            'slug' => str_repeat('a', 250),
            'sku' => str_repeat('1', 20),
        ]);
    }

    #[Test]
    public function a_second_home_page_is_refused_rather_than_suffixed(): void
    {
        Category::query()->create(['name' => 'Home', 'slug' => '']);

        $this->expectException(PathRejected::class);

        Category::query()->create(['name' => 'Home again', 'slug' => '']);
    }
}
