<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Admin\Doctor\Checks\Relations as RelationsCheck;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ResolvesForEntity;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Tests\Fixtures\Categories\EntrySource;
use WebxUi\Admin\Tests\Fixtures\Categories\Section;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Admin\Tests\Fixtures\Relations\Chef;
use WebxUi\Admin\Tests\Fixtures\Relations\Dish;
use WebxUi\Admin\Tests\Fixtures\Relations\DishSource;

/**
 * Relations between records (§3 of the recipes spec) on two fixtures: dishes — drafted, like a
 * recipe — pointing at chefs — a module of their own, like services — and at each other.
 */
final class RelationsTest extends TestCase
{
    private const SCREEN = 'kitchen.dish-form';

    protected function setUp(): void
    {
        parent::setUp();

        $targets = $this->app->make(RelationTargets::class);
        $targets->register(new RelationTarget('chef', Chef::class, 'kitchen.view', 'Chef'));
        $targets->register(new RelationTarget('dish', Dish::class, 'kitchen.view', 'Dish'));

        Screens::register(self::SCREEN, [
            ['id' => 'title', 'type' => 'wx-input', 'name' => 'title', 'label' => 'Title'],
            ['id' => 'chefs', 'type' => 'wx-relations', 'name' => 'chefs', 'label' => 'Chefs', 'props' => ['target' => 'chef', 'max' => 3]],
            ['id' => 'related', 'type' => 'wx-relations', 'name' => 'related', 'label' => 'Like it', 'props' => ['target' => 'dish']],
            ['id' => 'waiters', 'type' => 'wx-relations', 'name' => 'waiters', 'label' => 'Waiters', 'props' => ['target' => 'waiter']],
        ]);
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
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

        Schema::create('dish_flavour', static function (Blueprint $table): void {
            $table->categoryLinks('dish', 'sections');
        });
    }

    #[Test]
    public function a_relation_keeps_the_order_it_was_chosen_in_without_repeats_or_itself(): void
    {
        [$anna, $boris, $clara] = [$this->chef('Anna'), $this->chef('Boris'), $this->chef('Clara')];
        $soup = $this->dish('Soup');
        $salad = $this->dish('Salad');

        $soup->syncRelated('chefs', 'chef', [$clara->id, $anna->id, $clara->id]);
        $soup->syncRelated('related', 'dish', [$soup->id, $salad->id]);

        $this->assertSame(['Clara', 'Anna'], $soup->related('chefs')->pluck('title')->all());
        $this->assertSame([$clara->id, $anna->id], $soup->relatedIds('chefs'));
        $this->assertSame([$salad->id], $soup->relatedIds('related'));
        $this->assertNotContains($boris->id, $soup->relatedIds('chefs'));
    }

    #[Test]
    public function a_list_loads_one_role_in_a_query_per_role_and_per_target_type(): void
    {
        $anna = $this->chef('Anna');
        $boris = $this->chef('Boris');
        $dishes = [$this->dish('Soup'), $this->dish('Salad'), $this->dish('Stew')];

        $dishes[0]->syncRelated('chefs', 'chef', [$anna->id]);
        $dishes[1]->syncRelated('chefs', 'chef', [$boris->id, $anna->id]);

        $fresh = Dish::query()->orderBy('id')->get();

        DB::enableQueryLog();
        Relations::load($fresh, 'chefs');
        $loaded = count(DB::getQueryLog());

        $titles = $fresh->map(static fn (Dish $dish): array => $dish->related('chefs')->pluck('title')->all())->all();
        DB::disableQueryLog();

        $this->assertSame(2, $loaded);
        $this->assertCount(2, DB::getQueryLog(), 'reading the loaded relations asks nothing more');
        $this->assertSame([['Anna'], ['Boris', 'Anna'], []], $titles);
    }

    #[Test]
    public function the_other_end_asks_who_points_at_it(): void
    {
        $anna = $this->chef('Anna');
        $soup = $this->dish('Soup');
        $this->dish('Salad');
        $stew = $this->dish('Stew');

        $soup->syncRelated('chefs', 'chef', [$anna->id]);
        $stew->syncRelated('chefs', 'chef', [$anna->id]);

        $this->assertSame(['Soup', 'Stew'], Relations::owners('dish', 'chefs', $anna)->orderBy('id')->pluck('title')->all());
        $this->assertSame(['Soup', 'Stew'], Dish::query()->relatedTo('chefs', 'chef', $anna->id)->orderBy('id')->pluck('title')->all());
        $this->assertSame([], Dish::query()->relatedTo('related', 'chef', $anna->id)->pluck('title')->all());
    }

    #[Test]
    public function deleting_for_good_takes_the_rows_along_on_both_ends_and_the_bin_does_not(): void
    {
        $anna = $this->chef('Anna');
        $boris = $this->chef('Boris');
        $soup = $this->dish('Soup');
        $salad = $this->dish('Salad');

        $soup->syncRelated('chefs', 'chef', [$anna->id, $boris->id]);
        $salad->syncRelated('chefs', 'chef', [$anna->id]);
        $boris->syncRelated('signature', 'dish', [$soup->id]);

        $anna->delete();
        $this->assertSame(4, $this->rows(), 'the bin keeps them');
        $this->assertSame(['Boris'], $this->reload($soup)->related('chefs')->pluck('title')->all(), 'but the site does not read them');

        $anna->restore();
        $this->assertSame(['Anna', 'Boris'], $this->reload($soup)->related('chefs')->pluck('title')->all());

        $anna->forceDelete();
        $this->assertSame(2, $this->rows(), 'the target end');

        $soup->forceDelete();
        $this->assertSame(0, $this->rows(), 'the owner end, and what pointed at the owner');
    }

    #[Test]
    public function a_module_that_is_not_installed_keeps_its_rows_and_is_read_as_absent(): void
    {
        $anna = $this->chef('Anna');
        $soup = $this->dish('Soup');
        $soup->syncRelated('chefs', 'chef', [$anna->id]);

        $targets = $this->app->make(RelationTargets::class);
        $targets->forget();
        $targets->register(new RelationTarget('dish', Dish::class));

        $fresh = $this->reload($soup);
        $this->assertSame([], $fresh->related('chefs')->all());
        $this->assertSame([$anna->id], $fresh->relatedIds('chefs'), 'the value stays whole for the module to come back');

        $diagnoses = $this->app->make(RelationsCheck::class)->run();
        $this->assertCount(1, $diagnoses);
        $this->assertTrue($diagnoses[0]->warned());
        $this->assertStringContainsString('1 of dish point at chef', $diagnoses[0]->detail);
    }

    #[Test]
    public function the_site_reads_only_what_it_shows_when_asked_to(): void
    {
        $anna = $this->chef('Anna');
        $boris = $this->chef('Boris', hidden: true);
        $soup = $this->dish('Soup');
        $soup->syncRelated('chefs', 'chef', [$boris->id, $anna->id]);

        $this->assertSame(['Boris', 'Anna'], $soup->related('chefs')->pluck('title')->all());
        $this->assertSame(['Anna'], $soup->related('chefs', visible: true)->pluck('title')->all());
    }

    #[Test]
    public function the_field_keeps_ids_that_exist_once_each_and_refuses_too_many(): void
    {
        $anna = $this->chef('Anna');
        $gone = $this->chef('Gone');
        $gone->delete();

        $node = ['type' => 'wx-relations', 'name' => 'chefs', 'props' => ['target' => 'chef', 'max' => 2]];

        $this->assertSame([$gone->id, $anna->id], $this->type()->store([(string) $gone->id, $anna->id, $anna->id, 999, 'x', -1], $node));

        $validator = validator(['value' => [1, 2, 3]], ['value' => $this->type()->rules($node)]);
        $this->assertTrue($validator->fails());
        $this->assertSame(['No more than 2 can be chosen.'], $validator->errors()->all());
    }

    #[Test]
    public function a_target_nobody_answers_for_takes_the_field_off_the_screen_and_leaves_the_value(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $names = array_column($screens->fields(self::SCREEN), 'name');

        $this->assertSame(['title', 'chefs', 'related'], $names);

        $this->actingAs(new Editor)
            ->getJson('/api/cms/screens/'.self::SCREEN)
            ->assertOk()
            ->assertJsonCount(3, 'data.root');

        $soup = $this->dish('Soup');
        Relations::rows($soup)->insert([
            'owner_type' => 'dish', 'owner_id' => $soup->id, 'role' => 'waiters',
            'target_type' => 'waiter', 'target_id' => 5, 'position' => 0,
        ]);

        $split = $this->record()->split(self::SCREEN, ['title' => 'Soup', 'waiters' => [7]], own: ['title']);
        $this->record()->saveRelations($soup, $split);

        $this->assertSame([], $split->extra, 'a withdrawn field is not a field of the project either');
        $this->assertSame([5], $soup->relatedIds('waiters'));
    }

    #[Test]
    public function a_drafted_record_takes_its_relations_when_it_is_published(): void
    {
        $anna = $this->chef('Anna');
        $boris = $this->chef('Boris');
        $soup = $this->dish('Soup');
        $salad = $this->dish('Salad');
        $soup->syncRelated('chefs', 'chef', [$anna->id]);

        $split = $this->record()->split(self::SCREEN, [
            'title' => 'Soup',
            'chefs' => [$boris->id, $anna->id],
            'related' => [$salad->id],
        ], own: ['title']);

        $this->assertSame(['title' => 'Soup'], $split->own);
        $this->assertSame([], $split->extra);
        $this->assertSame([
            'chefs' => ['target' => 'chef', 'ids' => [$boris->id, $anna->id]],
            'related' => ['target' => 'dish', 'ids' => [$salad->id]],
        ], $split->relations);

        $this->record()->saveRelations($soup, $split);

        $this->assertSame([$anna->id], $soup->relatedIds('chefs'), 'saved is not published');
        $this->assertSame(
            ['chefs' => [$boris->id, $anna->id], 'related' => [$salad->id]],
            $this->record()->relationValues(self::SCREEN, $soup),
            'the form opens with what the editor left',
        );
        $this->assertSame(['Boris', 'Anna'], $soup->withDraft()->related('chefs')->pluck('title')->all(), 'the preview reads the draft');
        $this->assertSame(['Anna'], $soup->related('chefs')->pluck('title')->all(), 'the site does not');

        $soup->publish();

        $this->assertSame([$boris->id, $anna->id], $soup->relatedIds('chefs'));
        $this->assertSame([$salad->id], $soup->relatedIds('related'));
        $this->assertFalse($this->reload($soup)->hasDraft());
    }

    #[Test]
    public function putting_a_relation_back_the_way_the_site_has_it_leaves_no_draft(): void
    {
        $anna = $this->chef('Anna');
        $boris = $this->chef('Boris');
        $soup = $this->dish('Soup');
        $soup->syncRelated('chefs', 'chef', [$anna->id]);

        $soup->saveRelations(['chefs' => ['target' => 'chef', 'ids' => [$boris->id]]]);
        $this->assertTrue($soup->hasDraft());

        $soup->saveRelations(['chefs' => ['target' => 'chef', 'ids' => [$anna->id]]]);
        $this->assertFalse($this->reload($soup)->hasDraft());
    }

    #[Test]
    public function a_record_without_a_draft_takes_its_relations_at_once(): void
    {
        $boris = $this->chef('Boris');
        $soup = $this->dish('Soup');

        $boris->saveRelations(['signature' => ['target' => 'dish', 'ids' => [$soup->id]]]);

        $this->assertSame([$soup->id], $boris->relatedIds('signature'));
    }

    #[Test]
    public function the_picker_finds_candidates_and_names_the_chosen_behind_the_targets_permission(): void
    {
        $anna = $this->chef('Anna');
        $this->chef('Boris');
        $hidden = $this->chef('Annabel', hidden: true);
        $gone = $this->chef('Annette');
        $gone->delete();

        $editor = new Editor(['kitchen.view']);

        $this->actingAs($editor)
            ->getJson('/api/cms/relations/chef?q=ann')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $anna->id, 'title' => 'Anna', 'subtitle' => null, 'thumb' => null, 'visible' => true, 'trashed' => false],
                ['id' => $hidden->id, 'title' => 'Annabel', 'subtitle' => null, 'thumb' => null, 'visible' => false, 'trashed' => false],
            ]]);

        $this->actingAs($editor)
            ->getJson('/api/cms/relations/chef?q=ann&except[]='.$anna->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($editor)
            ->getJson('/api/cms/relations/chef?ids[]='.$gone->id.'&ids[]='.$anna->id.'&ids[]=999')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Annette')
            ->assertJsonPath('data.0.visible', false)
            ->assertJsonPath('data.0.trashed', true)
            ->assertJsonPath('data.1.id', $anna->id)
            ->assertJsonCount(2, 'data');

        $this->actingAs(new Editor)->getJson('/api/cms/relations/chef')->assertForbidden();
        $this->actingAs($editor)->getJson('/api/cms/relations/waiter')->assertNotFound();
    }

    #[Test]
    public function a_second_kind_of_category_is_written_and_filtered_by_its_name(): void
    {
        $starters = $this->section('Starters');
        $sweet = $this->section('Sweet');
        $soup = $this->dish('Soup');
        $this->dish('Salad');

        $soup->syncCategories([$starters->id]);
        $soup->syncCategories([$sweet->id], 'flavours');

        $this->assertSame([$starters->id], $soup->sections()->pluck('sections.id')->all());
        $this->assertSame([$sweet->id], $soup->flavours()->pluck('sections.id')->all());
        $this->assertSame(['Soup'], Dish::query()->inCategory($sweet->id, 'flavours')->pluck('title')->all());
        $this->assertSame([], Dish::query()->inCategory($sweet->id)->pluck('title')->all());
        $this->assertSame($starters->id, $this->reload($soup)->mainCategory()?->getKey(), 'the main category is the default relation’s');
    }

    #[Test]
    public function a_block_keeps_a_relation_filter_only_where_its_source_has_one(): void
    {
        $this->registerSource();
        $anna = $this->chef('Anna');

        $node = ['type' => 'wx-collection', 'name' => 'dishes', 'props' => ['source' => 'dishes']];
        $collection = $this->collection();

        $this->assertSame(
            ['categories' => [], 'limit' => null, 'filter' => false, 'markup' => null, 'related' => ['type' => 'chef', 'ids' => [$anna->id, 3]]],
            $collection->store(['related' => ['type' => 'chef', 'ids' => [3, (string) $anna->id, 3]]], $node),
        );
        $this->assertSame(
            ['categories' => [], 'limit' => null, 'filter' => false, 'markup' => null, 'related' => ['type' => 'chef', 'ids' => [], 'current' => true]],
            $collection->store(['related' => ['type' => 'chef', 'ids' => [3], 'current' => true]], $node),
        );
        $this->assertNull($collection->store(['related' => ['type' => 'chef', 'ids' => []]], $node)['related'], 'no ids, no filter');
        $this->assertNull($collection->store(['related' => ['type' => 'waiter', 'ids' => [1]]], $node)['related'], 'not a relation of this source');
        $this->assertNull(Selection::normalise(['related' => ['type' => 'chef', 'ids' => [1]]], new EntrySource)['related'], 'a source without relations never sees one');

        $this->assertSame(['Records of this section cannot be filtered by that.'], $this->errors(['related' => ['type' => 'waiter', 'ids' => [1]]], $node));
        $this->assertSame(['One of the chosen related records no longer exists.'], $this->errors(['related' => ['type' => 'chef', 'ids' => [$anna->id, 999]]], $node));
        $this->assertSame([], $this->errors(['related' => ['type' => 'chef', 'ids' => [], 'current' => true]], $node));
    }

    #[Test]
    public function a_block_shows_the_records_related_to_the_chosen_ones_or_to_its_page(): void
    {
        $this->registerSource();
        $anna = $this->chef('Anna');
        $boris = $this->chef('Boris');
        $soup = $this->dish('Soup');
        $salad = $this->dish('Salad');
        $this->dish('Stew');

        $soup->syncRelated('chefs', 'chef', [$anna->id]);
        $salad->syncRelated('chefs', 'chef', [$boris->id]);

        $node = ['type' => 'wx-collection', 'name' => 'dishes', 'props' => ['source' => 'dishes']];
        $collection = $this->collection();
        $titles = static fn (array $read): array => array_column($read['items'], 'title');

        $this->assertSame(['Soup', 'Salad', 'Stew'], $titles($collection->resolve(null, $node, 'en')));
        $this->assertSame(['Soup'], $titles($collection->resolve(['related' => ['type' => 'chef', 'ids' => [$anna->id]]], $node, 'en')));

        $current = ['related' => ['type' => 'chef', 'ids' => [], 'current' => true]];

        $this->assertSame(['Salad'], $titles($collection->resolveFor($current, $node, $boris, 'en')), 'on the page of a chef');
        $this->assertSame([], $titles($collection->resolveFor($current, $node, $soup, 'en')), 'on a page of another kind');
        $this->assertSame(['Soup', 'Salad', 'Stew'], $titles($collection->resolveFor($current, $node, null, 'en')), 'on its sample');
    }

    #[Test]
    public function the_panel_is_told_what_a_source_can_be_filtered_by_relation_to(): void
    {
        $this->registerSource();

        $this->actingAs(new Editor)
            ->getJson('/api/cms/collections')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'dishes')
            ->assertJsonPath('data.0.relations', [['key' => 'chef', 'title' => 'Chef']]);

        $this->app->make(RelationTargets::class)->forget();

        $this->actingAs(new Editor)
            ->getJson('/api/cms/collections')
            ->assertJsonPath('data.0.relations', []);
    }

    private function registerSource(): void
    {
        $this->app->make(CollectionSources::class)->register(new DishSource);
        $this->app->make(CategorySources::class)->register('things/sections', Section::class);
    }

    private function type(): FieldType
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-relations');
        $this->assertNotNull($type);

        return $type;
    }

    private function collection(): ResolvesForEntity
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-collection');
        $this->assertInstanceOf(ResolvesForEntity::class, $type);

        return $type;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function errors(mixed $value, array $node): array
    {
        $validator = validator(['value' => $value], ['value' => $this->collection()->rules($node)]);

        try {
            $validator->validate();
        } catch (ValidationException) {
            return $validator->errors()->all();
        }

        return [];
    }

    private function record(): ScreenRecord
    {
        return $this->app->make(ScreenRecord::class);
    }

    private function rows(): int
    {
        return DB::table(Relations::TABLE)->count();
    }

    private function chef(string $title, bool $hidden = false): Chef
    {
        return Chef::query()->create(['title' => $title, 'retired' => $hidden]);
    }

    private function dish(string $title): Dish
    {
        $dish = Dish::query()->create(['title' => $title]);
        $dish->publish();

        return $dish;
    }

    /** The row read again, with nothing remembered from before — what the next request sees. */
    private function reload(Dish $dish): Dish
    {
        return Dish::query()->withTrashed()->findOrFail($dish->id);
    }

    private function section(string $title): Section
    {
        return Section::query()->create(['title' => ['en' => $title], 'slug' => ['en' => strtolower($title)]]);
    }
}
