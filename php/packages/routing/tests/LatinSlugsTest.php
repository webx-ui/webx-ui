<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Tests\Fixtures\Category;

/**
 * A site without `module-seo`: the slug moves all the same, the registry's alias answers, and the
 * command says that it wrote no redirect rather than leaving that to be found out.
 */
final class LatinSlugsTest extends TestCase
{
    #[Test]
    public function without_the_seo_module_the_slug_moves_and_the_report_says_no_redirect_was_written(): void
    {
        $category = Category::query()->create(['name' => 'Parts', 'slug' => 'parts']);

        DB::table($category->getTable())->where('id', $category->id)->update(['slug' => 'детали']);
        Route::query()->forEntity($category)->update(['path' => 'детали']);

        $this->artisan('webx:slugs:latin', ['--apply' => true])
            ->expectsOutputToContain('module-seo is not installed')
            ->assertSuccessful();

        $this->assertSame('detali', $category->refresh()->slug);
        $this->assertSame('detali', Route::query()->forEntity($category)->where('kind', Route::CANONICAL)->value('path'));
        $this->assertSame('детали', Route::query()->forEntity($category)->where('kind', Route::ALIAS)->value('path'));
    }

    #[Test]
    public function a_slug_with_no_languages_is_held_to_the_rule_too(): void
    {
        $category = Category::query()->create(['name' => 'Parts', 'slug' => '漢字']);

        $this->assertSame("category-{$category->id}", $category->refresh()->slug);
        $this->assertSame("category-{$category->id}", Route::query()->forEntity($category)->where('kind', Route::CANONICAL)->value('path'));
    }
}
