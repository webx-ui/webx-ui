<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Tests\Fixtures\Categories\Section;
use WebxUi\Admin\Tests\Fixtures\Relations\Chef;
use WebxUi\Admin\Tests\Fixtures\Relations\ChefQuery;
use WebxUi\Admin\Tests\Fixtures\Relations\Dish;
use WebxUi\Admin\Tests\Fixtures\Relations\DishQuery;

/**
 * The rules every site helper keeps the same way (§4.2 of the team spec): chefs stand for a
 * module without categories — a team — and dishes for one with categories named by slug.
 */
final class RecordQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(RelationTargets::class)->register(new RelationTarget('dish', Dish::class, 'kitchen.view', 'Dish'));
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru']]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('sections', static function (Blueprint $table): void {
            $table->id();
            $table->category();
        });

        Schema::create('chefs', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('retired')->default(false);
            $table->integer('position')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('dishes', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->integer('position')->default(0);
            $table->draft();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('dish_section', static function (Blueprint $table): void {
            $table->categoryLinks('dish', 'sections');
        });
    }

    #[Test]
    public function a_model_without_categories_is_listed_in_its_one_order_and_the_limit_counts_what_is_shown(): void
    {
        $this->chef('Clara', 2);
        $this->chef('Anna-ru', 0);
        $this->chef('Boris', 1);
        $this->chef('Retired', 3, retired: true);
        $this->chef('Dora', 4);

        $this->assertSame(['Anna-ru', 'Boris', 'Clara', 'Dora'], $this->titles((new ChefQuery)->locale('ru')));
        $this->assertSame(['Boris', 'Clara', 'Dora'], $this->titles((new ChefQuery)->locale('en')));

        // The first two in English are two English ones, not two minus the Russian one.
        $this->assertSame(['Boris', 'Clara'], $this->titles((new ChefQuery)->locale('en')->take(2)));
        $this->assertSame(['Boris', 'Clara', 'Dora'], $this->titles((new ChefQuery)->locale('en')->take('0')));
    }

    #[Test]
    public function only_sets_the_order_and_except_adds_up(): void
    {
        $anna = $this->chef('Anna', 0);
        $boris = $this->chef('Boris', 1);
        $clara = $this->chef('Clara', 2);

        $query = (new ChefQuery)->locale('en');

        $this->assertSame(['Clara', 'Anna'], $this->titles($query->only([$clara->id, (string) $anna->id])));
        $this->assertSame([], $this->titles($query->only([])));
        $this->assertSame(['Clara'], $this->titles($query->except($anna)->except([$boris->id])));
        $this->assertSame('Boris', $query->except($anna)->first()['title'] ?? null);
        $this->assertSame(3, count($query));
        $this->assertFalse($query->isEmpty());
    }

    #[Test]
    public function every_step_returns_a_new_query(): void
    {
        $this->chef('Anna', 0);
        $this->chef('Boris', 1);

        $all = (new ChefQuery)->locale('en');
        $one = $all->take(1);

        $this->assertNotSame($all, $one);
        $this->assertCount(2, $all->get());
        $this->assertCount(1, $one->get());
    }

    #[Test]
    public function related_to_nothing_is_nothing_and_to_something_is_what_points_at_it(): void
    {
        $anna = $this->chef('Anna', 0);
        $this->chef('Boris', 1);
        $soup = $this->dish('Soup');

        $anna->syncRelated('dishes', 'dish', [$soup->id]);

        $query = (new ChefQuery)->locale('en');

        $this->assertSame(['Anna'], $this->titles($query->relatedTo('dish', $soup)));
        $this->assertSame([], $this->titles($query->relatedTo('dish', [])));
    }

    #[Test]
    public function an_untouched_category_filter_is_everything_and_a_slug_nobody_has_is_nothing(): void
    {
        $starters = $this->section('Starters');
        $mains = $this->section('Mains');
        $soup = $this->dish('Soup', 0);
        $stew = $this->dish('Stew', 1);
        $this->dish('Cake', 2);

        $soup->syncCategories([$starters->id]);
        $stew->syncCategories([$mains->id, $starters->id]);

        $query = (new DishQuery)->locale('en');

        $this->assertSame(['Soup', 'Stew', 'Cake'], $this->titles($query->in(null)));
        $this->assertSame(['Soup', 'Stew', 'Cake'], $this->titles($query->in([])));
        $this->assertSame(['Soup', 'Stew', 'Cake'], $this->titles($query->in('  ')));
        $this->assertSame(['Stew'], $this->titles($query->in('mains')));
        $this->assertSame(['Stew'], $this->titles($query->in([$mains, 'nobody'])));
        $this->assertSame([], $this->titles($query->in('nobody')));
        $this->assertSame(['Soup', 'Stew'], $this->titles($query->in([$starters->id, (string) $mains->id])));
    }

    #[Test]
    public function a_selection_without_categories_works_on_a_model_that_has_none(): void
    {
        $this->chef('Clara', 2);
        $this->chef('Anna', 0);

        $titles = (new Selection([], 5))->apply(Chef::query())->pluck('title')->all();

        $this->assertSame(['Anna', 'Clara'], $titles);

        $this->expectException(InvalidArgumentException::class);
        (new Selection([3]))->apply(Chef::query());
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $cards
     * @return list<mixed>
     */
    private function titles(iterable $cards): array
    {
        $titles = [];

        foreach ($cards as $card) {
            $titles[] = $card['title'];
        }

        return $titles;
    }

    private function chef(string $title, int $position, bool $retired = false): Chef
    {
        return Chef::query()->create(['title' => $title, 'position' => $position, 'retired' => $retired]);
    }

    private function dish(string $title, int $position = 0): Dish
    {
        $dish = Dish::query()->create(['title' => $title, 'position' => $position]);
        $dish->publish();

        return $dish;
    }

    private function section(string $title): Section
    {
        return Section::query()->create(['title' => ['en' => $title], 'slug' => ['en' => strtolower($title)]]);
    }
}
