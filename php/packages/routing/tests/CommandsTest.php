<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Formatters\SlugId;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Tests\Fixtures\Category;

/**
 * The two commands (§11): one changes the address scheme of a type, the other says when the
 * registry has stopped telling the truth.
 */
class CommandsTest extends TestCase
{
    #[Test]
    public function a_dry_run_shows_the_change_and_writes_nothing(): void
    {
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);

        $this->changeTheScheme();

        $this->artisan('webx:routes:rebuild', ['--type' => 'category', '--dry-run' => true])
            ->expectsOutputToContain('/parts')
            ->assertSuccessful();

        $this->assertSame('parts', Route::query()->forEntity($category)->canonical()->value('path'));
        $this->assertSame(1, Route::query()->forEntity($category)->count());
    }

    #[Test]
    public function a_rebuild_moves_the_addresses_and_leaves_aliases(): void
    {
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);

        $this->changeTheScheme();

        $this->artisan('webx:routes:rebuild', ['--type' => 'category'])->assertSuccessful();

        // The point of the command: the scheme changed and not one external link died for it.
        $this->assertSame('parts-'.$category->getKey(), Route::query()->forEntity($category)->canonical()->value('path'));
        $this->assertSame('parts', Route::query()->forEntity($category)->alias()->value('path'));
    }

    #[Test]
    public function a_rebuild_with_nothing_to_do_says_so(): void
    {
        Category::create(['name' => 'Parts', 'slug' => 'parts']);

        $this->artisan('webx:routes:rebuild')
            ->expectsOutputToContain('already matches')
            ->assertSuccessful();
    }

    #[Test]
    public function check_is_quiet_when_every_address_is_in_step(): void
    {
        Category::create(['name' => 'Parts', 'slug' => 'parts']);
        $this->page('about');

        $this->artisan('webx:routes:check')->assertSuccessful();
    }

    #[Test]
    public function check_finds_a_row_whose_entity_is_gone(): void
    {
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);

        // Straight through the query builder, which is how this happens in the wild: a cleanup
        // script, a truncate, a restore of one table out of two.
        DB::table('categories')->where('id', $category->getKey())->delete();

        $this->artisan('webx:routes:check')
            ->expectsOutputToContain('the entity is gone')
            ->assertFailed();
    }

    #[Test]
    public function check_finds_an_entity_with_no_address(): void
    {
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);

        Route::query()->forEntity($category)->delete();

        $this->artisan('webx:routes:check')
            ->expectsOutputToContain('no address')
            ->assertFailed();
    }

    #[Test]
    public function check_finds_an_alias_that_leads_nowhere(): void
    {
        $category = Category::create(['name' => 'Parts', 'slug' => 'parts']);

        Route::query()->forEntity($category)->update(['kind' => Route::ALIAS, 'target_id' => null]);

        $this->artisan('webx:routes:check')
            ->expectsOutputToContain('the alias leads nowhere')
            ->assertFailed();
    }

    #[Test]
    public function check_finds_an_address_the_project_has_since_claimed(): void
    {
        Category::create(['name' => 'Parts', 'slug' => 'parts']);

        // The address was free when the editor saved it. Then somebody shipped a controller.
        $this->app['config']->set('webx-routing.reserved', ['parts']);

        $this->artisan('webx:routes:check')
            ->expectsOutputToContain('answers this address itself')
            ->assertFailed();
    }

    /** What a site does when it decides its categories should carry their key after all. */
    private function changeTheScheme(): void
    {
        $this->app['config']->set('webx-routing.types.category.formatter', SlugId::class);
    }
}
