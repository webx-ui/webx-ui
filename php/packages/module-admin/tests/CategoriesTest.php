<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Tests\Fixtures\Categories\EnsurePermission;
use WebxUi\Admin\Tests\Fixtures\Categories\Entry;
use WebxUi\Admin\Tests\Fixtures\Categories\Section;
use WebxUi\Admin\Tests\Fixtures\Editor;

/**
 * The categories every module shares (§3 of the services spec), on a module that is nobody's:
 * sections without addresses, and entries ordered by hand — what a FAQ would be.
 *
 * The part worth reading twice is the two orders. An entry's categories are in the order they
 * were given, the first being the main one; an entry's place inside one category is kept when it
 * is saved again, and a new one is placed by the order of the whole list, so a category nobody
 * rearranged lists its entries exactly as the whole list does.
 */
final class CategoriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Screens::register(Section::SCREEN, __DIR__.'/Fixtures/screens/section-form.json');

        $this->app['router']->aliasMiddleware('cms.can', EnsurePermission::class);

        Route::prefix('api/cms/things')->name('things.')->group(static function (): void {
            CategoryRoutes::register(Section::class, 'sections');
            CategoryRoutes::items(Entry::class, 'entries', 'things.manage');
        });
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'ru'],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('sections', static function (Blueprint $table): void {
            $table->id();
            $table->category();
        });

        Schema::create('entries', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('entry_category', static function (Blueprint $table): void {
            $table->categoryLinks('entry', 'sections');
        });
    }

    #[Test]
    public function the_macros_lay_down_the_columns_the_code_reads(): void
    {
        $this->assertTrue(Schema::hasColumns('sections', ['title', 'slug', 'position', 'is_visible', 'extra', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('entry_category', ['entry_id', 'category_id', 'position', 'item_position']));
    }

    #[Test]
    public function a_new_category_goes_to_the_end_of_the_list(): void
    {
        $this->section('First');
        $this->section('Second');

        $this->assertSame([1, 2], Section::query()->ordered()->pluck('position')->all());
    }

    #[Test]
    public function the_first_category_of_an_entry_is_its_main_one(): void
    {
        $faq = $this->section('FAQ');
        $billing = $this->section('Billing');
        $entry = $this->entry('Refunds', 0);

        $entry->syncCategories([$billing->getKey(), $faq->getKey()]);

        $this->assertSame($billing->getKey(), $entry->refresh()->mainCategory()?->getKey());
        $this->assertSame([$billing->getKey(), $faq->getKey()], $entry->sections->modelKeys());
    }

    #[Test]
    public function a_new_entry_takes_its_place_in_a_category_by_the_order_of_the_whole_list(): void
    {
        $faq = $this->section('FAQ');
        $first = $this->entry('First', 0);
        $third = $this->entry('Third', 2);
        $second = $this->entry('Second', 1);

        foreach ([$first, $third, $second] as $entry) {
            $entry->syncCategories([$faq->getKey()]);
        }

        $this->assertSame(
            [$first->getKey(), $second->getKey(), $third->getKey()],
            Entry::query()->orderedIn($faq->getKey())->pluck('id')->all(),
        );
    }

    #[Test]
    public function an_entry_saved_again_keeps_the_place_somebody_dragged_it_to(): void
    {
        $faq = $this->section('FAQ');
        $billing = $this->section('Billing');
        $first = $this->entry('First', 0);
        $second = $this->entry('Second', 1);

        $first->syncCategories([$faq->getKey()]);
        $second->syncCategories([$faq->getKey()]);

        // Dragged with the filter on: second above first, in this category only.
        Ordering::move(Entry::class, [$second->getKey(), $first->getKey()], $faq->getKey());

        // Filed into one more category — the place in the first one must survive that.
        $second->syncCategories([$faq->getKey(), $billing->getKey()]);

        $this->assertSame([$second->getKey(), $first->getKey()], Entry::query()->orderedIn($faq->getKey())->pluck('id')->all());
        // The whole list is untouched by a drag inside one category.
        $this->assertSame([$first->getKey(), $second->getKey()], Entry::query()->orderedIn()->pluck('id')->all());
        $this->assertSame([$second->getKey()], Entry::query()->inCategory($billing->getKey())->pluck('id')->all());
    }

    #[Test]
    public function the_whole_list_is_reordered_without_a_category_and_one_category_with_it(): void
    {
        $faq = $this->section('FAQ');
        $first = $this->entry('First', 0);
        $second = $this->entry('Second', 1);
        $first->syncCategories([$faq->getKey()]);
        $second->syncCategories([$faq->getKey()]);

        $this->actingAs(new Editor(['things.manage']))
            ->postJson('/api/cms/things/entries/reorder', ['ids' => [$second->getKey(), $first->getKey()]])
            ->assertNoContent();

        $this->assertSame([$second->getKey(), $first->getKey()], Entry::query()->orderedIn()->pluck('id')->all());
        $this->assertSame([$first->getKey(), $second->getKey()], Entry::query()->orderedIn($faq->getKey())->pluck('id')->all());

        $this->actingAs(new Editor(['things.manage']))
            ->postJson('/api/cms/things/entries/reorder', ['ids' => [$second->getKey(), $first->getKey()], 'category' => $faq->getKey()])
            ->assertNoContent();

        $this->assertSame([$second->getKey(), $first->getKey()], Entry::query()->orderedIn($faq->getKey())->pluck('id')->all());
    }

    #[Test]
    public function a_category_with_entries_in_it_refuses_to_go_and_says_how_many(): void
    {
        $faq = $this->section('FAQ');
        $this->entry('One', 0)->syncCategories([$faq->getKey()]);
        $this->entry('Two', 1)->syncCategories([$faq->getKey()]);

        $response = $this->actingAs(new Editor(['things.manage']))
            ->deleteJson('/api/cms/things/sections/'.$faq->getKey())
            ->assertStatus(422);

        $this->assertMatchesRegularExpression('/\b2\b/', (string) $response->json('message'));
        $this->assertNotNull(Section::query()->find($faq->getKey()));

        $this->expectException(CategoryException::class);
        $faq->delete();
    }

    #[Test]
    public function an_empty_category_goes_into_the_bin_and_comes_back(): void
    {
        $faq = $this->section('FAQ');
        $editor = new Editor(['things.manage']);

        $this->actingAs($editor)->deleteJson('/api/cms/things/sections/'.$faq->getKey())->assertNoContent();
        $this->assertNull(Section::query()->find($faq->getKey()));

        $this->actingAs($editor)
            ->postJson('/api/cms/things/sections/'.$faq->getKey().'/restore')
            ->assertOk()
            ->assertJsonPath('data.id', $faq->getKey());

        $this->assertNotNull(Section::query()->find($faq->getKey()));
    }

    #[Test]
    public function the_list_arrives_in_order_with_the_count_under_the_modules_word(): void
    {
        $faq = $this->section('FAQ');
        $billing = $this->section('Billing');
        $this->entry('One', 0)->syncCategories([$billing->getKey()]);

        Ordering::move(Section::class, [$billing->getKey(), $faq->getKey()]);

        $response = $this->actingAs(new Editor(['things.view']))
            ->getJson('/api/cms/things/sections')
            ->assertOk();

        $this->assertSame(['Billing', 'FAQ'], array_column((array) $response->json('data'), 'name'));
        $this->assertSame(1, $response->json('data.0.entries_count'));
        $this->assertNull($response->json('data.0.path'));
        $this->assertNull($response->json('prefix'));

        $this->actingAs(new Editor(['things.view']))
            ->getJson('/api/cms/things/sections?q=bill')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Reading is not writing.
        $this->actingAs(new Editor(['things.view']))
            ->postJson('/api/cms/things/sections', ['title' => 'Nope'])
            ->assertForbidden();
    }

    #[Test]
    public function a_category_is_made_from_its_name_alone(): void
    {
        $this->actingAs(new Editor(['things.manage']))
            ->postJson('/api/cms/things/sections', ['title' => 'Spare parts'])
            ->assertCreated()
            ->assertJsonPath('data.slug.en', 'spare-parts')
            ->assertJsonPath('data.title.en', 'Spare parts');

        $this->actingAs(new Editor(['things.manage']))
            ->postJson('/api/cms/things/sections', ['title' => ['en' => 'Odd'], 'slug' => ['en' => 'two words']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug.en']);
    }

    /**
     * The fields of the project (§3.4): patched onto the screen, kept in `extra`, merged rather
     * than replaced, language by language for a localized one, and read through their type.
     */
    #[Test]
    public function a_field_the_project_patched_in_is_kept_in_extra(): void
    {
        Screens::extend(Section::SCREEN, [
            ['op' => 'add', 'target' => 'project-fields', 'node' => ['id' => 'motto', 'type' => 'wx-input', 'name' => 'motto', 'label' => 'Motto', 'localized' => true]],
            ['op' => 'add', 'target' => 'project-fields', 'node' => ['id' => 'featured', 'type' => 'wx-switch', 'name' => 'featured', 'label' => 'Featured']],
        ]);

        $faq = $this->section('FAQ');
        $editor = new Editor(['things.manage']);
        $url = '/api/cms/things/sections/'.$faq->getKey();

        $this->actingAs($editor)
            ->putJson($url, ['values' => ['motto' => ['en' => 'Ask away', 'ru' => 'Спрашивайте'], 'featured' => '1']])
            ->assertOk()
            ->assertJsonPath('data.values.motto.en', 'Ask away')
            ->assertJsonPath('data.values.featured', true);

        // One language, and another field: neither the other language nor the switch is lost.
        $this->actingAs($editor)
            ->putJson($url, ['values' => ['motto' => ['en' => 'Ask us'], 'title' => ['en' => 'Questions']]])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Questions');

        $faq->refresh();

        $this->assertSame(['en' => 'Ask us', 'ru' => 'Спрашивайте'], $faq->extraRaw('motto'));
        $this->assertTrue($faq->extraRaw('featured'));
        $this->assertSame('Ask us', $faq->extra('motto', 'en'));
        $this->assertSame('Спрашивайте', $faq->extra('motto', 'ru'));

        // A key the screen does not name is dropped at the door, not stored.
        $this->actingAs($editor)->putJson($url, ['values' => ['smuggled' => 'x']])->assertOk();
        $this->assertNull($faq->refresh()->extraRaw('smuggled'));
    }

    #[Test]
    public function a_field_the_patch_no_longer_draws_keeps_its_value(): void
    {
        $faq = $this->section('FAQ');
        $faq->forceFill(['extra' => ['old-field' => 'kept']])->save();

        $this->actingAs(new Editor(['things.manage']))
            ->putJson('/api/cms/things/sections/'.$faq->getKey(), ['values' => ['is_visible' => false]])
            ->assertOk()
            ->assertJsonPath('data.values.is_visible', false);

        $this->assertSame('kept', $faq->refresh()->extra('old-field'));
    }

    #[Test]
    public function wx_categories_checks_the_ids_against_the_model_its_source_names(): void
    {
        $this->app->make(CategorySources::class)->register('things/sections', Section::class);

        $faq = $this->section('FAQ');
        $billing = $this->section('Billing');
        $billing->delete();

        $type = $this->app->make(FieldTypes::class)->get('wx-categories');
        $this->assertNotNull($type);

        $check = function (array $node, mixed $value) use ($type): array {
            return validator(['rubrics' => $value], ['rubrics' => $type->rules($node)])->errors()->all();
        };

        $node = ['id' => 'x', 'type' => 'wx-categories', 'name' => 'rubrics', 'props' => ['source' => '/things/sections/']];

        // One in the bin still counts: its records are still filed under it.
        $this->assertSame([], $check($node, [$faq->getKey(), $billing->getKey()]));
        $this->assertSame([], $check($node, []));
        $this->assertNotSame([], $check($node, [$faq->getKey(), 9001]));

        // A source nobody registered refuses rather than letting ids through unchecked.
        $this->assertNotSame([], $check(['props' => ['source' => 'nowhere']] + $node, [$faq->getKey()]));

        // The order is the value; repeats and junk are dropped.
        $this->assertSame([3, 1], $type->store([3, '1', 3, 'x', null], $node));
    }

    private function section(string $title): Section
    {
        return Section::query()->create(['title' => ['en' => $title], 'slug' => ['en' => strtolower($title)]]);
    }

    private function entry(string $name, int $position): Entry
    {
        return Entry::query()->create(['name' => $name, 'position' => $position]);
    }
}
