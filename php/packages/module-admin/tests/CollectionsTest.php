<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Categories\CategorySources;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Tests\Fixtures\Categories\Entry;
use WebxUi\Admin\Tests\Fixtures\Categories\EntrySource;
use WebxUi\Admin\Tests\Fixtures\Categories\Section;
use WebxUi\Admin\Tests\Fixtures\Editor;

/**
 * `wx-collection` (§3 of the FAQ spec) on a source that is nobody's: entries filed under
 * sections, the shape a FAQ has. What is kept is the choice; what the site reads is the records,
 * in the order the choice implies, with a filter made of the categories they are in.
 */
final class CollectionsTest extends TestCase
{
    private EntrySource $source;

    /** @var array<string, mixed> */
    private array $node = ['type' => 'wx-collection', 'name' => 'entries', 'props' => ['source' => 'entries']];

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new EntrySource;
        $this->app->make(CollectionSources::class)->register($this->source);
        $this->app->make(CategorySources::class)->register('things/sections', Section::class);
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
    public function what_is_kept_is_the_choice_cleaned(): void
    {
        $this->assertSame(
            ['categories' => [3, 5], 'limit' => 100, 'filter' => true, 'markup' => false, 'related' => null],
            $this->type()->store(['categories' => ['5', 3, 5, 'x'], 'limit' => 500, 'filter' => true, 'markup' => false, 'source' => 'reviews'], $this->node),
        );

        $this->assertSame(
            ['categories' => [], 'limit' => null, 'filter' => false, 'markup' => null, 'related' => null],
            $this->type()->store(['limit' => 0, 'markup' => 'yes'], $this->node),
        );

        $this->assertNull($this->type()->store('everything', $this->node));

        // What the source cannot do is not kept: a choice nothing will act on is a choice that
        // surprises whoever turns the source's markup on a year later.
        $this->app->make(CollectionSources::class)->register(new EntrySource(markup: false));

        $this->assertSame(
            ['categories' => [], 'limit' => null, 'filter' => false, 'markup' => null, 'related' => null],
            $this->type()->store(['markup' => true], $this->node),
        );
    }

    #[Test]
    public function the_rules_refuse_what_the_source_would_not_know(): void
    {
        $billing = $this->section('Billing');

        $this->assertSame([], $this->errors(['categories' => [$billing->id], 'limit' => 10, 'filter' => true, 'markup' => null]));
        $this->assertSame([], $this->errors(null));

        $this->assertStringContainsString('no longer exists', $this->errors(['categories' => [$billing->id, 999]])[0] ?? '');
        $this->assertStringContainsString('from 1 to 100', $this->errors(['limit' => 101])[0] ?? '');
        $this->assertStringContainsString('yes or no', $this->errors(['filter' => 'on'])[0] ?? '');

        $reviews = ['type' => 'wx-collection', 'name' => 'entries', 'props' => ['source' => 'reviews']];
        $this->assertStringContainsString('“reviews”', $this->errors(['limit' => 5], $reviews)[0] ?? '');
    }

    #[Test]
    public function one_category_reads_in_its_own_order_and_several_in_the_order_of_the_whole_list(): void
    {
        $billing = $this->section('Billing');
        $delivery = $this->section('Delivery');

        $refunds = $this->entry('Refunds', 1, [$billing, $delivery]);
        $cards = $this->entry('Cards', 2, [$billing]);
        $couriers = $this->entry('Couriers', 3, [$delivery]);
        $this->entry('Unfiled', 4, []);

        // Inside Billing, Cards was dragged above Refunds.
        $billing->items()->updateExistingPivot($cards->id, ['item_position' => 0]);
        $billing->items()->updateExistingPivot($refunds->id, ['item_position' => 5]);

        $this->assertSame(['Cards', 'Refunds'], $this->names(['categories' => [$billing->id]]));
        $this->assertSame(['Refunds', 'Cards', 'Couriers'], $this->names(['categories' => [$delivery->id, $billing->id]]), 'each once, by the whole list');
        $this->assertSame(['Refunds', 'Cards', 'Couriers', 'Unfiled'], $this->names(null), 'a block nobody touched shows everything');
        $this->assertSame(['Refunds', 'Cards'], $this->names(['limit' => 2]));
    }

    #[Test]
    public function the_filter_is_the_visible_categories_with_something_shown_in_them(): void
    {
        $billing = $this->section('Billing');
        $delivery = $this->section('Delivery');
        $empty = $this->section('Empty');
        $hidden = $this->section('Hidden');
        $hidden->update(['is_visible' => false]);

        $refunds = $this->entry('Refunds', 1, [$delivery, $billing, $hidden]);
        $cards = $this->entry('Cards', 2, [$billing]);
        $this->entry('', 3, [$empty]);

        $read = $this->type()->resolve(['filter' => true], $this->node, 'en');

        $this->assertTrue($read['filter']);
        $this->assertSame([
            ['id' => $billing->id, 'title' => 'Billing', 'items' => [$refunds->id, $cards->id]],
            ['id' => $delivery->id, 'title' => 'Delivery', 'items' => [$refunds->id]],
        ], $read['groups']);

        $chosen = $this->type()->resolve(['categories' => [$delivery->id], 'filter' => true], $this->node, 'en');
        $this->assertSame([$delivery->id], array_column($chosen['groups'], 'id'), 'chosen categories only');

        $this->assertSame([], $this->type()->resolve(['filter' => false], $this->node, 'en')['groups']);
    }

    #[Test]
    public function markup_by_default_is_on_for_everything_and_off_for_chosen_categories(): void
    {
        $billing = $this->section('Billing');

        $this->assertTrue($this->asked(null)->markup);
        $this->assertFalse($this->asked(['categories' => [$billing->id]])->markup);
        $this->assertTrue($this->asked(['categories' => [$billing->id], 'markup' => true])->markup, 'the editor may turn it on');
        $this->assertFalse($this->asked(['markup' => false])->markup);

        $this->app->make(CollectionSources::class)->register($source = new EntrySource(markup: false));
        $this->type()->resolve(null, $this->node, 'en');

        $this->assertFalse($source->asked?->markup, 'a source without markup is never asked for it');
    }

    #[Test]
    public function a_source_that_is_gone_reads_as_nothing(): void
    {
        $this->entry('Refunds', 1, []);

        $this->app->make(CollectionSources::class)->forget();

        $this->assertSame(['items' => [], 'groups' => [], 'filter' => false], $this->type()->resolve(['filter' => true], $this->node, 'en'));
    }

    #[Test]
    public function the_panel_is_told_the_sources_this_administrator_may_place(): void
    {
        $this->actingAs(new Editor(['things.view']))
            ->getJson('/api/cms/collections')
            ->assertOk()
            ->assertExactJson(['data' => [['key' => 'entries', 'title' => 'Entries', 'categories' => 'things/sections', 'markup' => true, 'relations' => []]]]);

        $this->actingAs(new Editor(['pages.view']))
            ->getJson('/api/cms/collections')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    private function type(): FieldType
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-collection');
        $this->assertNotNull($type);

        return $type;
    }

    /**
     * @param  array<string, mixed>|null  $node
     * @return list<string>
     */
    private function errors(mixed $value, ?array $node = null): array
    {
        $validator = validator(['value' => $value], ['value' => $this->type()->rules($node ?? $this->node)]);

        try {
            $validator->validate();
        } catch (ValidationException) {
            return $validator->errors()->all();
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function names(mixed $stored): array
    {
        /** @var list<array{name: string}> $items */
        $items = $this->type()->resolve($stored, $this->node, 'en')['items'];

        return array_column($items, 'name');
    }

    private function asked(mixed $stored): Selection
    {
        $this->type()->resolve($stored, $this->node, 'en');
        $this->assertNotNull($this->source->asked);

        return $this->source->asked;
    }

    private function section(string $title): Section
    {
        return Section::query()->create(['title' => ['en' => $title], 'slug' => ['en' => strtolower($title)]]);
    }

    /**
     * @param  list<Section>  $sections
     */
    private function entry(string $name, int $position, array $sections): Entry
    {
        $entry = Entry::query()->create(['name' => $name, 'position' => $position]);
        $entry->syncCategories(array_map(static fn (Section $section): int => $section->id, $sections));

        return $entry;
    }
}
